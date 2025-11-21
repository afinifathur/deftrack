<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\ImportSession;
use App\Models\Department;
use App\Imports\BatchXlsxImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class BatchImportController extends Controller
{
    /**
     * List import sessions (aktif).
     */
    public function index()
    {
        $sessions = ImportSession::withCount('batches')
            ->orderByDesc('date')
            ->paginate(20);

        return view('imports.index', compact('sessions'));
    }

    /**
     * Form upload import.
     */
    public function create()
    {
        $departments = Department::where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('imports.create', compact('departments'));
    }

    /**
     * Store import (CSV / XLSX) -> membuat ImportSession + Batch (create / update)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'          => 'required|date',
            'department_id' => 'required|exists:departments,id',
            'file'          => 'required|file|mimes:csv,txt,xlsx,xls',
            'note'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            return redirect()->back()->with('error', 'File tidak valid.');
        }

        $ins = 0;
        $upd = 0;

        try {
            // Baca file menjadi array baris canonical
            $rows = $this->parseUploadedFile($file);

            if (empty($rows)) {
                throw new \RuntimeException('File tidak berisi data baris yang valid.');
            }

            DB::beginTransaction();

            try {
                $session = ImportSession::create([
                    'date'          => Carbon::parse($request->date)->toDateString(),
                    'department_id' => $request->department_id,
                    'created_by'    => auth()->id() ?? 1,
                    'note'          => $request->note,
                ]);

                $seen = [];

                foreach ($rows as $row) {
                    $hn = trim((string) ($row['heat_number'] ?? ''));
                    $ic = trim((string) ($row['item_code'] ?? ''));

                    // Skip jika heat_number / item_code kosong
                    if ($hn === '' || $ic === '') {
                        continue;
                    }

                    // Skip duplikat di file (heat_number + item_code)
                    $key = $hn . '||' . $ic;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $itemName    = $row['item_name'] ?? null;
                    $weightPerPc = $this->toFloat($row['weight_per_pc'] ?? null);
                    $batchQty    = isset($row['batch_qty']) ? (int) $row['batch_qty'] : 0;
                    $castRaw     = $row['cast_date'] ?? null;

                    // Jika cast_date kosong / gagal parse -> fallback ke tanggal session
                    $castDate = $castRaw
                        ? $this->normalizeDate($castRaw, $session->date)
                        : $session->date;

                    $aisi     = $row['aisi'] ?? null;
                    $size     = $row['size'] ?? null;
                    $line     = $row['line'] ?? null;
                    $custName = $row['cust_name'] ?? null;

                    $batchData = [
                        'item_name'         => $itemName !== '' ? $itemName : null,
                        'weight_per_pc'     => $weightPerPc !== null ? $weightPerPc : 0,
                        'batch_qty'         => $batchQty,
                        'cast_date'         => $castDate,
                        'import_session_id' => $session->id,
                        'aisi'              => $aisi ?: null,
                        'size'              => $size ?: null,
                        'line'              => $line ?: null,
                        'cust_name'         => $custName ?: null,
                    ];

                    // Cek existing batch berdasarkan heat_number + item_code
                    $existingBatch = Batch::where('heat_number', $hn)
                        ->where('item_code', $ic)
                        ->first();

                    if ($existingBatch) {
                        // Update field kecuali batch_code
                        $existingBatch->fill(
                            array_filter($batchData, fn($v) => $v !== null || $v === 0)
                        );
                        $existingBatch->import_session_id = $session->id;
                        $existingBatch->save();
                        $upd++;
                    } else {
                        // Buat batch baru, sequence per session
                        $seqCount  = $session->batches()->lockForUpdate()->count();
                        $seq       = $seqCount + 1;
                        $batchCode = $this->generateBatchCodeForSession($session->date, $seq);

                        $createData = array_merge([
                            'heat_number' => $hn,
                            'item_code'   => $ic,
                            'batch_code'  => $batchCode,
                        ], $batchData);

                        $batch                      = new Batch();
                        $batch->batch_code          = $createData['batch_code'] ?? null;
                        $batch->heat_number         = $createData['heat_number'] ?? null;
                        $batch->item_code           = $createData['item_code'] ?? null;
                        $batch->item_name           = $createData['item_name'] ?? null;
                        $batch->weight_per_pc       = $createData['weight_per_pc'] ?? 0;
                        $batch->batch_qty           = $createData['batch_qty'] ?? 0;
                        $batch->cast_date           = $createData['cast_date'] ?? null;
                        $batch->import_session_id   = $createData['import_session_id'] ?? $session->id;
                        $batch->aisi                = $createData['aisi'] ?? null;
                        $batch->size                = $createData['size'] ?? null;
                        $batch->line                = $createData['line'] ?? null;
                        $batch->cust_name           = $createData['cust_name'] ?? null;
                        $batch->save();

                        $ins++;
                    }
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            return redirect()
                ->back()
                ->with('error', 'Terjadi error saat mengimpor file: ' . $e->getMessage());
        }

        return redirect()
            ->route('imports.index')
            ->with('status', "Import selesai. Insert: {$ins}, Update: {$upd}");
    }

    /**
     * Tampilkan detail satu import session.
     */
    public function show(ImportSession $importSession)
    {
        $batches = $importSession->batches()
            ->orderBy('heat_number')
            ->get();

        return view('imports.show', compact('importSession', 'batches'));
    }

    /**
     * Form edit batch hasil import.
     */
    public function edit(ImportSession $importSession)
    {
        $batches = $importSession->batches()
            ->orderBy('heat_number')
            ->get();

        return view('imports.edit', compact('importSession', 'batches'));
    }

    /**
     * Update batches dari form edit.
     * Expect: $request->input('batches') => [id => [...fields...]]
     */
    public function update(Request $request, ImportSession $importSession)
    {
        $data = $request->input('batches', []);

        if (! is_array($data) || count($data) === 0) {
            return back()->with('error', 'Tidak ada batch yang dikirim untuk diperbarui.');
        }

        DB::beginTransaction();

        try {
            foreach ($data as $id => $row) {
                $row = (array) $row;

                $rowValidator = Validator::make($row, [
                    'heat_number'   => 'required|string',
                    'item_code'     => 'required|string',
                    'item_name'     => 'nullable|string',
                    'weight_per_pc' => 'nullable|numeric',
                    'batch_qty'     => 'nullable|integer',
                    'cast_date'     => 'nullable|date',
                    'aisi'          => 'nullable|string',
                    'size'          => 'nullable|string',
                    'line'          => 'nullable|string',
                    'cust_name'     => 'nullable|string',
                ]);

                if ($rowValidator->fails()) {
                    // Bisa disimpan untuk feedback per-row jika diperlukan
                    continue;
                }

                $batch = Batch::where('id', $id)
                    ->where('import_session_id', $importSession->id)
                    ->first();

                if (! $batch) {
                    continue;
                }

                $batch->heat_number = $row['heat_number'];
                $batch->item_code   = $row['item_code'];
                $batch->item_name   = $row['item_name'] ?? $batch->item_name;

                $batch->weight_per_pc = array_key_exists('weight_per_pc', $row) && $row['weight_per_pc'] !== ''
                    ? (float) $row['weight_per_pc']
                    : $batch->weight_per_pc;

                $batch->batch_qty = array_key_exists('batch_qty', $row) && $row['batch_qty'] !== ''
                    ? (int) $row['batch_qty']
                    : $batch->batch_qty;

                if (! empty($row['cast_date'])) {
                    try {
                        $batch->cast_date = Carbon::parse($row['cast_date'])->toDateString();
                    } catch (\Throwable $e) {
                        // abaikan invalid date
                    }
                }

                // Field tambahan, boleh kosong untuk clear
                if (array_key_exists('aisi', $row)) {
                    $batch->aisi = $row['aisi'] === '' ? null : $row['aisi'];
                }
                if (array_key_exists('size', $row)) {
                    $batch->size = $row['size'] === '' ? null : $row['size'];
                }
                if (array_key_exists('line', $row)) {
                    $batch->line = $row['line'] === '' ? null : $row['line'];
                }
                if (array_key_exists('cust_name', $row)) {
                    $batch->cust_name = $row['cust_name'] === '' ? null : $row['cust_name'];
                }

                $batch->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->with('error', 'Terjadi kesalahan saat memperbarui batch: ' . $e->getMessage());
        }

        return back()->with('success', 'Batch hasil import berhasil diperbarui.');
    }

    /**
     * Soft delete session + batches.
     */
    public function destroy(ImportSession $importSession)
    {
        DB::transaction(function () use ($importSession) {
            // soft delete batches dulu, lalu session
            $importSession->batches()->delete();
            $importSession->delete();
        });

        return back()->with('status', 'Import session dihapus.');
    }

    /**
     * Recycle Bin: list ImportSession yang sudah di-soft-delete.
     */
    public function recycle()
    {
        $deleted = ImportSession::onlyTrashed()
            ->withCount([
                // hitung juga batches yang ter-soft delete
                'batches' => fn($q) => $q->withTrashed(),
            ])
            ->orderByDesc('deleted_at')
            ->get();

        return view('imports.recycle', compact('deleted'));
    }

    /**
     * Restore ImportSession + batches yang terhapus (soft delete).
     */
    public function restore($id)
    {
        $session = ImportSession::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($session) {
            $session->restore();
            // restore semua batch yang terkait (yang ter-soft delete)
            $session->batches()->withTrashed()->restore();
        });

        return back()->with('success', 'Import session berhasil dikembalikan.');
    }

    /**
     * Hapus permanen ImportSession + batches (force delete).
     */
    public function forceDelete($id)
    {
        $session = ImportSession::onlyTrashed()->findOrFail($id);

        DB::transaction(function () use ($session) {
            // hapus permanen semua batch yang terkait (termasuk yang ter-soft delete)
            $session->batches()->withTrashed()->forceDelete();
            $session->forceDelete();
        });

        return back()->with('success', 'Import session dihapus permanen.');
    }

    /**
     * Batch code format: CR-YYYYMMDD-XX
     */
    protected function generateBatchCodeForSession(string $sessionDate, int $seq): string
    {
        $d   = Carbon::parse($sessionDate)->format('Ymd');
        $idx = str_pad($seq, 2, '0', STR_PAD_LEFT);

        return "CR-{$d}-{$idx}";
    }

    /**
     * Normalize date, fallback jika gagal parse.
     */
    protected function normalizeDate($value, $fallback)
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    /**
     * Konversi numeric string (pakai koma/titik) menjadi float.
     */
    protected function toFloat($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace([' ', ','], ['', '.'], trim((string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }

    /**
     * Deteksi extension dan delegasikan ke parser yang sesuai.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array<int, array<string, mixed>>
     */
    protected function parseUploadedFile($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->parseXlsxToRows($file);
        }

        // fallback CSV/TXT
        return $this->parseCsvToRows($file);
    }

    /**
     * Parse XLSX/XLS via Laravel Excel.
     *
     * Mengandalkan BatchXlsxImport untuk mengembalikan array baris
     * dengan key canonical:
     * heat_number, item_code, item_name, weight_per_pc, batch_qty,
     * cast_date, aisi, size, line, cust_name
     */
    protected function parseXlsxToRows($file): array
    {
        $sheets = Excel::toArray(new BatchXlsxImport, $file);

        $rows = $sheets[0] ?? [];

        // Buang baris kosong total
        $rows = array_filter($rows, function ($row) {
            if (! is_array($row)) {
                return false;
            }

            foreach ($row as $value) {
                if (trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        });

        return array_values($rows);
    }

    /**
     * Parse CSV/TXT -> array baris canonical (heat_number, item_code, dst).
     */
    protected function parseCsvToRows($file): array
    {
        $path   = $file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file CSV.');
        }

        try {
            // --- HEADER HANDLING ---
            $rawHeader = null;

            while (! feof($handle)) {
                $line = fgets($handle);
                if ($line === false) {
                    break;
                }

                $cells = str_getcsv($line, ',', '"', '\\');

                // skip baris kosong
                if (is_array($cells) && count(array_filter($cells, fn($c) => trim((string) $c) !== '')) === 0) {
                    continue;
                }

                $rawHeader = $cells;
                break;
            }

            if ($rawHeader === null) {
                throw new \RuntimeException('File CSV kosong atau rusak (header tidak ditemukan).');
            }

            // normalisasi header
            $header = array_map(function ($h) {
                $h = (string) $h;
                $h = preg_replace('/^\x{FEFF}/u', '', $h); // remove BOM
                return Str::of($h)->lower()->trim()->replace([' ', '.'], '_')->__toString();
            }, $rawHeader);

            // peta nama kolom variatif ke canonical
            $canonicalMap = [
                'heat_number'   => ['heat_number', 'heatno', 'heat_no', 'hn', 'heat'],
                'item_code'     => ['item_code', 'code', 'itemcode', 'kode_item', 'kode'],
                'item_name'     => ['item_name', 'name', 'itemname', 'nama_item'],
                'weight_per_pc' => ['weight_per_pc', 'weight', 'w_per_pc', 'weight_per_piece', 'berat'],
                'batch_qty'     => ['batch_qty', 'qty', 'quantity', 'jumlah'],
                'cast_date'     => ['cast_date', 'castdate', 'tgl_cast', 'tanggal_cast', 'tanggal'],
                'aisi'          => ['aisi', 'a_i_s_i', 'a.i.s.i'],
                'size'          => ['size', 'ukuran'],
                'line'          => ['line', 'production_line', 'l'],
                'cust_name'     => ['cust_name', 'customer', 'cust', 'customer_name', 'nama_customer'],
            ];

            // map index header -> canonical key
            $map = [];
            foreach ($header as $i => $h) {
                foreach ($canonicalMap as $canon => $aliases) {
                    if (in_array($h, $aliases, true)) {
                        $map[$canon] = $i;
                        break;
                    }
                }
            }

            if (! isset($map['heat_number']) || ! isset($map['item_code'])) {
                throw new \RuntimeException('CSV harus memiliki kolom Heat Number dan Item Code.');
            }

            $headerCount = count($header);

            // --- READ ROWS ---
            $rawRows = [];
            $badRows = [];

            while (! feof($handle)) {
                $raw = fgets($handle);
                if ($raw === false) {
                    break;
                }

                $peekCells = str_getcsv($raw, ',', '"', '\\');
                if (is_array($peekCells) && count(array_filter($peekCells, fn($c) => trim((string) $c) !== '')) === 0) {
                    continue;
                }

                $combined = $raw;
                $cells    = str_getcsv($combined, ',', '"', '\\');
                $attempts = 0;

                // coba gabung max 10 baris jika kolom kurang
                while (count($cells) < $headerCount && ! feof($handle) && $attempts < 10) {
                    $nextLine = fgets($handle);
                    if ($nextLine === false) {
                        break;
                    }

                    $combined .= "\n" . $nextLine;
                    $cells     = str_getcsv($combined, ',', '"', '\\');
                    $attempts++;
                }

                if (count($cells) < $headerCount) {
                    $badRows[] = [
                        'line' => 'approx:' . (count($rawRows) + 2),
                        'cols' => $cells,
                        'raw'  => $combined,
                    ];
                    continue;
                }

                $cells     = array_map(fn($c) => is_null($c) ? '' : trim((string) $c), $cells);
                $rawRows[] = $cells;
            }

            if (count($badRows) > 0) {
                \Log::warning(
                    'Import CSV has rows with unexpected column count (auto-merge failed)',
                    ['bad_rows_sample' => array_slice($badRows, 0, 5)]
                );

                $firstBad = $badRows[0];

                throw new \RuntimeException(
                    'Beberapa baris CSV memiliki jumlah kolom tidak konsisten (contoh baris: ' .
                    $firstBad['line'] .
                    '). Periksa tanda petik ganda (") di file CSV atau perbaiki CSV sebelum meng-upload. ' .
                    'Lihat storage/logs/laravel.log untuk detail.'
                );
            }

            // Konversi ke rows asosiatif (canonical)
            $rows = [];

            foreach ($rawRows as $cells) {
                $rows[] = [
                    'heat_number'   => $cells[$map['heat_number']] ?? null,
                    'item_code'     => $cells[$map['item_code']] ?? null,
                    'item_name'     => isset($map['item_name']) ? ($cells[$map['item_name']] ?? null) : null,
                    'weight_per_pc' => isset($map['weight_per_pc']) ? ($cells[$map['weight_per_pc']] ?? null) : null,
                    'batch_qty'     => isset($map['batch_qty']) ? ($cells[$map['batch_qty']] ?? null) : null,
                    'cast_date'     => isset($map['cast_date']) ? ($cells[$map['cast_date']] ?? null) : null,
                    'aisi'          => isset($map['aisi']) ? ($cells[$map['aisi']] ?? null) : null,
                    'size'          => isset($map['size']) ? ($cells[$map['size']] ?? null) : null,
                    'line'          => isset($map['line']) ? ($cells[$map['line']] ?? null) : null,
                    'cust_name'     => isset($map['cust_name']) ? ($cells[$map['cust_name']] ?? null) : null,
                ];
            }

            return $rows;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }
}
