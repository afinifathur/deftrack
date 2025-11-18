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
     * - Membuat ImportSession
     * - Memproses CSV baris demi baris
     * - Untuk setiap baris: update jika batch ada, atau create baru
     * - Generate batch_code sekuensial per import session (hanya saat create)
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

        // create import session
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

        // prepare header mapping
        try {
            $rawHeader = fgetcsv($handle);
            if ($rawHeader === false) {
                fclose($handle);
                return redirect()->back()->with('error', 'File CSV kosong atau rusak.');
            }

            $header = array_map(function ($h) {
                return Str::of($h ?? '')->lower()->trim()->replace(' ', '_')->__toString();
            }, $rawHeader);

            $canonicalMap = [
                'heat_number'   => ['heat_number', 'heatno', 'heat_no', 'hn', 'heat'],
                'item_code'     => ['item_code', 'code', 'itemcode', 'kode_item', 'kode'],
                'item_name'     => ['item_name', 'name', 'itemname', 'nama_item'],
                'weight_per_pc' => ['weight_per_pc', 'weight', 'w_per_pc', 'weight_per_piece', 'berat'],
                'batch_qty'     => ['batch_qty', 'qty', 'quantity', 'jumlah'],
                'cast_date'     => ['cast_date', 'castdate', 'tgl_cast', 'tanggal_cast', 'tanggal'],
            ];

            $map = [];
            foreach ($header as $i => $h) {
                foreach ($canonicalMap as $canon => $aliases) {
                    if (in_array($h, $aliases, true)) {
                        $map[$canon] = $i;
                    }
                }
            }

            // minimal required columns
            if (!isset($map['heat_number']) || !isset($map['item_code'])) {
                fclose($handle);
                return redirect()->back()->with('error', 'CSV harus memiliki kolom Heat Number dan Item Code.');
            }

            // Start DB transaction for import
            DB::beginTransaction();

            // Start sequence counter for this import session.
            // We use per-session sequence so batch_code unik per session and determinstic.
            $seq = $session->batches()->count();

            while (($row = fgetcsv($handle)) !== false) {
                // skip empty lines
                if (count(array_filter($row, fn($c) => trim((string)$c) !== '')) === 0) {
                    continue;
                }

                $hn = isset($map['heat_number']) ? trim($row[$map['heat_number']] ?? '') : '';
                $ic = isset($map['item_code']) ? trim($row[$map['item_code']] ?? '') : '';

                if ($hn === '' || $ic === '') {
                    // skip baris tanpa identifier penting
                    continue;
                }

                $itemName = isset($map['item_name']) ? trim($row[$map['item_name']] ?? '') : null;
                $weightPerPc = isset($map['weight_per_pc']) ? $this->toFloat($row[$map['weight_per_pc']] ?? 0) : null;
                $batchQty = isset($map['batch_qty']) ? (int) ($row[$map['batch_qty']] ?? 0) : null;
                $castDateRaw = isset($map['cast_date']) ? trim($row[$map['cast_date']] ?? '') : null;
                $castDate = $castDateRaw ? $this->normalizeDate($castDateRaw, $session->date) : $session->date;

                // find existing batch by unique identifiers (heat_number + item_code)
                $batch = Batch::where('heat_number', $hn)
                    ->where('item_code', $ic)
                    ->first();

                if ($batch) {
                    // update existing batch (jangan override batch_code)
                    $batch->item_name = $itemName ?: $batch->item_name;
                    $batch->weight_per_pc = $weightPerPc !== null ? $weightPerPc : $batch->weight_per_pc;
                    $batch->batch_qty = $batchQty ?: $batch->batch_qty;
                    $batch->cast_date = $castDate ?: $batch->cast_date;
                    $batch->import_session_id = $session->id;
                    $batch->save();

                    $upd++;
                } else {
                    // create new batch + generate batch_code (increment seq)
                    $seq++;
                    $batchCode = $this->generateBatchCodeForSession($session->date, $seq);

                    $batch = Batch::create([
                        'heat_number'      => $hn,
                        'item_code'        => $ic,
                        'item_name'        => $itemName,
                        'weight_per_pc'    => $weightPerPc ?? 0,
                        'batch_qty'        => $batchQty ?? 0,
                        'cast_date'        => $castDate,
                        'import_session_id'=> $session->id,
                        'batch_code'       => $batchCode,
                    ]);

                    $ins++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            fclose($handle);
            // optional: \Log::error('Batch import error: '.$e->getMessage(), ['exception' => $e]);
            return redirect()->back()->with('error', 'Terjadi error saat mengimpor CSV: ' . $e->getMessage());
        }

        fclose($handle);

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
                ]);

                if ($rowValidator->fails()) {
                    // skip invalid row (alternatif: abort dan tampilkan error)
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
                        // ignore invalid date and keep old value
                    }
                }

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
