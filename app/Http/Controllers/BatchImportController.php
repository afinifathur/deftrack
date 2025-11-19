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
    /**
     * Tampilkan daftar import session dengan jumlah batch.
     */
    public function index()
    {
        $sessions = ImportSession::withCount('batches')
            ->orderByDesc('date')
            ->paginate(20);

        return view('imports.index', compact('sessions'));
    }

    /**
     * Form untuk membuat import session baru.
     */
    public function create()
    {
        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        return view('imports.create', compact('departments'));
    }

    /**
     * Simpan import CSV dan batch ke database.
     *
     * Process:
     * - create ImportSession
     * - parse CSV (header mapping)
     * - for each row: update existing Batch (preserve batch_code) or create new Batch (generate batch_code)
     * - save new fields (aisi, size, line, cust_name) if present
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

        // Create import session record
        $session = ImportSession::create([
            'date'          => Carbon::parse($request->date)->toDateString(),
            'department_id' => $request->department_id,
            'created_by'    => auth()->id() ?? 1,
            'note'          => $request->note,
        ]);

        $ins = 0;
        $upd = 0;

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return redirect()->back()->with('error', 'File tidak valid.');
        }

        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return redirect()->back()->with('error', 'Gagal membuka file.');
        }

        try {
            // read header and normalize
            $rawHeader = fgetcsv($handle);
            if ($rawHeader === false) {
                throw new \RuntimeException('File CSV kosong atau rusak.');
            }

            $header = array_map(function ($h) {
                $h = (string) $h;
                // remove BOM if present
                $h = preg_replace('/^\x{FEFF}/u', '', $h);
                return Str::of($h)->lower()->trim()->replace([' ', '.'], '_')->__toString();
            }, $rawHeader);

            // canonical map including extra fields
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

            // map header positions to canonical keys
            $map = [];
            foreach ($header as $i => $h) {
                foreach ($canonicalMap as $canon => $aliases) {
                    if (in_array($h, $aliases, true)) {
                        $map[$canon] = $i;
                    }
                }
            }

            // require minimal columns
            if (!isset($map['heat_number']) || !isset($map['item_code'])) {
                throw new \RuntimeException('CSV harus memiliki kolom Heat Number dan Item Code.');
            }

            DB::beginTransaction();

            // starting sequence: count existing batches for session (should be 0 just after create)
            $seq = $session->batches()->count();

            while (($row = fgetcsv($handle)) !== false) {
                // skip empty lines
                if (count(array_filter($row, fn($c) => trim((string)$c) !== '')) === 0) {
                    continue;
                }

                $hn = isset($map['heat_number']) ? trim((string)($row[$map['heat_number']] ?? '')) : '';
                $ic = isset($map['item_code']) ? trim((string)($row[$map['item_code']] ?? '')) : '';

                if ($hn === '' || $ic === '') {
                    // skip rows without identifiers
                    continue;
                }

                $itemName    = isset($map['item_name'])      ? trim((string)($row[$map['item_name']] ?? '')) : null;
                $weightPerPc = isset($map['weight_per_pc']) ? $this->toFloat($row[$map['weight_per_pc']] ?? null) : null;
                $batchQty    = isset($map['batch_qty'])     ? (int) ($row[$map['batch_qty']] ?? 0) : null;
                $castRaw     = isset($map['cast_date'])     ? trim((string)($row[$map['cast_date']] ?? '')) : null;
                $castDate    = $castRaw ? $this->normalizeDate($castRaw, $session->date) : $session->date;

                // additional fields
                $aisi     = isset($map['aisi'])     ? trim((string)($row[$map['aisi']] ?? '')) : null;
                $size     = isset($map['size'])     ? trim((string)($row[$map['size']] ?? '')) : null;
                $line     = isset($map['line'])     ? trim((string)($row[$map['line']] ?? '')) : null;
                $custName = isset($map['cust_name'])? trim((string)($row[$map['cust_name']] ?? '')) : null;

                // find existing batch by heat_number + item_code
                $batch = Batch::where('heat_number', $hn)
                              ->where('item_code', $ic)
                              ->first();

                if ($batch) {
                    // update existing (preserve existing batch_code)
                    $batch->item_name = ($itemName !== null && $itemName !== '') ? $itemName : $batch->item_name;
                    $batch->weight_per_pc = $weightPerPc !== null ? $weightPerPc : $batch->weight_per_pc;
                    $batch->batch_qty = ($batchQty !== null && $batchQty !== 0) ? $batchQty : $batch->batch_qty;
                    $batch->cast_date = $castDate ?: $batch->cast_date;
                    // ensure association with this import session
                    $batch->import_session_id = $session->id;

                    // update additional fields only if provided in CSV (non-empty)
                    $batch->aisi = ($aisi !== null && $aisi !== '') ? $aisi : $batch->aisi;
                    $batch->size = ($size !== null && $size !== '') ? $size : $batch->size;
                    $batch->line = ($line !== null && $line !== '') ? $line : $batch->line;
                    $batch->cust_name = ($custName !== null && $custName !== '') ? $custName : $batch->cust_name;

                    $batch->save();
                    $upd++;
                } else {
                    // create new batch and generate batch_code for the session
                    $seq++;
                    $batchCode = $this->generateBatchCodeForSession($session->date, $seq);

                    $batch = Batch::create([
                        'heat_number'       => $hn,
                        'item_code'         => $ic,
                        'item_name'         => $itemName ?: null,
                        'weight_per_pc'     => $weightPerPc ?? 0,
                        'batch_qty'         => $batchQty ?? 0,
                        'cast_date'         => $castDate,
                        'import_session_id' => $session->id,
                        'batch_code'        => $batchCode,
                        'aisi'              => $aisi ?: null,
                        'size'              => $size ?: null,
                        'line'              => $line ?: null,
                        'cust_name'         => $custName ?: null,
                    ]);

                    $ins++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // make sure file handle closed before redirecting
            if (is_resource($handle)) {
                fclose($handle);
            }
            // \Log::error('Batch import error: '.$e->getMessage(), ['exception' => $e]);
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
     * Tampilkan detail import session beserta batch-nya.
     */
    public function show(ImportSession $importSession)
    {
        $batches = $importSession->batches()->orderBy('heat_number')->get();

        return view('imports.show', compact('importSession', 'batches'));
    }

    /**
     * Form edit untuk batch hasil import (menampilkan rows yang dapat diedit).
     */
    public function edit(ImportSession $importSession)
    {
        $batches = $importSession->batches()->orderBy('heat_number')->get();

        return view('imports.edit', compact('importSession', 'batches'));
    }

    /**
     * Update batch-batch yang dikirim dari form edit.
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
                $rowValidator = Validator::make((array)$row, [
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
                    // skip invalid row
                    continue;
                }

                $batch = Batch::where('id', $id)->where('import_session_id', $importSession->id)->first();
                if (!$batch) {
                    continue;
                }

                $batch->heat_number = $row['heat_number'];
                $batch->item_code = $row['item_code'];
                $batch->item_name = $row['item_name'] ?? $batch->item_name;
                $batch->weight_per_pc = isset($row['weight_per_pc']) ? (float)$row['weight_per_pc'] : $batch->weight_per_pc;
                $batch->batch_qty = isset($row['batch_qty']) ? (int)$row['batch_qty'] : $batch->batch_qty;

                if (!empty($row['cast_date'])) {
                    try {
                        $batch->cast_date = Carbon::parse($row['cast_date'])->toDateString();
                    } catch (\Throwable $e) {
                        // ignore invalid date
                    }
                }

                // additional fields
                $batch->aisi = array_key_exists('aisi', $row) ? ($row['aisi'] ?? $batch->aisi) : $batch->aisi;
                $batch->size = array_key_exists('size', $row) ? ($row['size'] ?? $batch->size) : $batch->size;
                $batch->line = array_key_exists('line', $row) ? ($row['line'] ?? $batch->line) : $batch->line;
                $batch->cust_name = array_key_exists('cust_name', $row) ? ($row['cust_name'] ?? $batch->cust_name) : $batch->cust_name;

                $batch->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // \Log::error('Batch update error: '.$e->getMessage());
            return back()->with('error', 'Terjadi kesalahan saat memperbarui batch: ' . $e->getMessage());
        }

        return back()->with('success', 'Batch hasil import berhasil diperbarui.');
    }

    /**
     * Hapus import session beserta batch-batchnya (soft delete jika model support).
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
     * Generate batch_code deterministik per session.
     * Format: CR-YYYYMMDD-XX (XX = 2-digit sequence)
     */
    protected function generateBatchCodeForSession(string $sessionDate, int $seq): string
    {
        $d = Carbon::parse($sessionDate)->format('Ymd');
        $idx = str_pad($seq, 2, '0', STR_PAD_LEFT);
        return "CR-{$d}-{$idx}";
    }

    /**
     * Helper: normalize date or fallback to default.
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
     * Helper: convert localized decimal with comma to float
     */
    protected function toFloat($value)
    {
        if ($value === null || $value === '') return null;
        $clean = str_replace([' ', ','], ['', '.'], trim((string)$value));
        return is_numeric($clean) ? (float)$clean : null;
    }
}
