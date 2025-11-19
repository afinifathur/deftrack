<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\ImportSession;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BatchImportController extends Controller
{
    public function index()
    {
        $sessions = ImportSession::withCount('batches')
            ->orderByDesc('date')
            ->paginate(20);

        return view('imports.index', compact('sessions'));
    }

    public function create()
    {
        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        return view('imports.create', compact('departments'));
    }

    /**
     * Store CSV import -> membuat ImportSession + Batch (create / update)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date'          => 'required|date',
            'department_id' => 'required|exists:departments,id',
            'file'          => 'required|file|mimes:csv,txt',
            'note'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'File tidak valid.');
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file.');
        }

        $ins = 0;
        $upd = 0;

        try {
            // baca header
            $rawHeader = fgetcsv($handle);
            if ($rawHeader === false) {
                throw new \RuntimeException('File CSV kosong atau rusak.');
            }

            // normalisasi header
            $header = array_map(function ($h) {
                $h = (string) $h;
                $h = preg_replace('/^\x{FEFF}/u', '', $h); // remove BOM
                return Str::of($h)->lower()->trim()->replace([' ', '.'], '_')->__toString();
            }, $rawHeader);

            // canonical map untuk menangani variasi nama kolom
            $canonicalMap = [
                'heat_number'   => ['heat_number', 'heatno', 'heat_no', 'hn', 'heat'],
                'item_code'     => ['item_code', 'code', 'itemcode', 'kode_item', 'kode'],
                'item_name'     => ['item_name', 'name', 'itemname', 'nama_item'],
                'weight_per_pc' => ['weight_per_pc', 'weight', 'w_per_pc', 'weight_per_piece', 'berat'],
                'batch_qty'     => ['batch_qty', 'qty', 'quantity', 'jumlah'],
                'cast_date'     => ['cast_date', 'castdate', 'tgl_cast', 'tanggal_cast', 'tanggal'],
                'aisi'          => ['aisi', 'a_i_s_i'],
                'size'          => ['size', 'ukuran'],
                'line'          => ['line', 'production_line', 'l'],
                'cust_name'     => ['cust_name', 'customer', 'cust', 'customer_name', 'nama_customer'],
            ];

            // buat map header index -> canonical key
            $map = [];
            foreach ($header as $i => $h) {
                foreach ($canonicalMap as $canon => $aliases) {
                    if (in_array($h, $aliases, true)) {
                        $map[$canon] = $i;
                        break;
                    }
                }
            }

            if (!isset($map['heat_number']) || !isset($map['item_code'])) {
                throw new \RuntimeException('CSV harus memiliki kolom Heat Number dan Item Code.');
            }

            // baca seluruh baris
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count(array_filter($row, fn($c) => trim((string)$c) !== '')) === 0) {
                    continue;
                }
                $rows[] = $row;
            }

            // proses di dalam transaction
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
                    $hn = isset($map['heat_number']) ? trim((string)($row[$map['heat_number']] ?? '')) : '';
                    $ic = isset($map['item_code']) ? trim((string)($row[$map['item_code']] ?? '')) : '';

                    if ($hn === '' || $ic === '') {
                        continue;
                    }

                    $key = $hn . '||' . $ic;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $itemName    = isset($map['item_name'])      ? trim((string)($row[$map['item_name']] ?? '')) : null;
                    $weightPerPc = isset($map['weight_per_pc']) ? $this->toFloat($row[$map['weight_per_pc']] ?? null) : null;
                    $batchQty    = isset($map['batch_qty'])     ? (int) ($row[$map['batch_qty']] ?? 0) : 0;
                    $castRaw     = isset($map['cast_date'])     ? trim((string)($row[$map['cast_date']] ?? '')) : null;
                    $castDate    = $castRaw ? $this->normalizeDate($castRaw, $session->date) : $session->date;

                    // fields tambahan
                    $aisi     = isset($map['aisi'])     ? trim((string)($row[$map['aisi']] ?? '')) : null;
                    $size     = isset($map['size'])     ? trim((string)($row[$map['size']] ?? '')) : null;
                    $line     = isset($map['line'])     ? trim((string)($row[$map['line']] ?? '')) : null;
                    $custName = isset($map['cust_name'])? trim((string)($row[$map['cust_name']] ?? '')) : null;

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

                    // cari existing batch by heat_number + item_code
                    $existingBatch = Batch::where('heat_number', $hn)
                        ->where('item_code', $ic)
                        ->first();

                    if ($existingBatch) {
                        // update fields (tidak override batch_code)
                        $existingBatch->fill(array_merge(
                            // hanya set field yang valid (include zeros)
                            array_filter($batchData, fn($v) => $v !== null || $v === 0)
                        ));
                        $existingBatch->import_session_id = $session->id;
                        $existingBatch->save();
                        $upd++;
                    } else {
                        // buat batch baru; hitung seq dengan lock untuk mencegah race
                        $seqCount = $session->batches()->lockForUpdate()->count();
                        $seq = $seqCount + 1;
                        $batchCode = $this->generateBatchCodeForSession($session->date, $seq);

                        $createData = array_merge([
                            'heat_number' => $hn,
                            'item_code'   => $ic,
                            'batch_code'  => $batchCode,
                        ], $batchData);

                        Batch::create($createData);
                        $ins++;
                    }
                } // end foreach rows

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return redirect()->back()->with('error', 'Terjadi error saat mengimpor CSV: ' . $e->getMessage());
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return redirect()->route('imports.index')
            ->with('status', "Import selesai. Insert: {$ins}, Update: {$upd}");
    }

    /**
     * Show session detail
     */
    public function show(ImportSession $importSession)
    {
        $batches = $importSession->batches()->orderBy('heat_number')->get();

        return view('imports.show', compact('importSession', 'batches'));
    }

    /**
     * Edit view -> menampilkan editable rows
     */
    public function edit(ImportSession $importSession)
    {
        $batches = $importSession->batches()->orderBy('heat_number')->get();

        return view('imports.edit', compact('importSession', 'batches'));
    }

    /**
     * Update batches dari form edit
     * Expect: $request->input('batches') => associative array [id => [...fields...]]
     */
    public function update(Request $request, ImportSession $importSession)
    {
        $data = $request->input('batches', []);

        if (!is_array($data) || count($data) === 0) {
            return back()->with('error', 'Tidak ada batch yang dikirim untuk diperbarui.');
        }

        DB::beginTransaction();
        try {
            foreach ($data as $id => $row) {
                // cast to array to be safe
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
                    // skip invalid row (alternatif: collect errors)
                    continue;
                }

                $batch = Batch::where('id', $id)
                    ->where('import_session_id', $importSession->id)
                    ->first();

                if (!$batch) {
                    continue;
                }

                $batch->heat_number = $row['heat_number'];
                $batch->item_code = $row['item_code'];
                $batch->item_name = $row['item_name'] ?? $batch->item_name;
                $batch->weight_per_pc = array_key_exists('weight_per_pc', $row) && $row['weight_per_pc'] !== '' 
                    ? (float)$row['weight_per_pc'] 
                    : $batch->weight_per_pc;
                $batch->batch_qty = array_key_exists('batch_qty', $row) && $row['batch_qty'] !== '' 
                    ? (int)$row['batch_qty'] 
                    : $batch->batch_qty;

                if (!empty($row['cast_date'])) {
                    try {
                        $batch->cast_date = Carbon::parse($row['cast_date'])->toDateString();
                    } catch (\Throwable $e) {
                        // ignore invalid date
                    }
                }

                // fields tambahan (jaga jika key ada, boleh kosong untuk clear)
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
     * Delete session + batches (soft delete jika model support)
     */
    public function destroy(ImportSession $importSession)
    {
        DB::transaction(function () use ($importSession) {
            $importSession->batches()->delete();
            $importSession->delete();
        });

        return back()->with('status', 'Import session dihapus.');
    }

    /**
     * Batch code format: CR-YYYYMMDD-XX
     */
    protected function generateBatchCodeForSession(string $sessionDate, int $seq): string
    {
        $d = Carbon::parse($sessionDate)->format('Ymd');
        $idx = str_pad($seq, 2, '0', STR_PAD_LEFT);
        return "CR-{$d}-{$idx}";
    }

    protected function normalizeDate($value, $fallback)
    {
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    protected function toFloat($value)
    {
        if ($value === null || $value === '') return null;
        $clean = str_replace([' ', ','], ['', '.'], trim((string)$value));
        return is_numeric($clean) ? (float)$clean : null;
    }
}
