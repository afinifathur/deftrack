<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Carbon\Carbon;
use App\Models\ImportSession;
use App\Models\Batch;

class BatchPasteImportController extends Controller
{
    /**
     * GET /imports/paste
     * Tampilkan form paste (Handsontable).
     */
    public function createPaste()
    {
        // contoh:
        // $departments = Department::orderBy('name')->get();
        // return view('imports.paste', compact('departments'));

        return view('imports.paste');
    }

    /**
     * POST /imports/paste (AJAX dari Handsontable)
     */
    public function storePaste(Request $request)
    {
        $payload = $request->all();

        // Validasi payload dasar
        $validator = Validator::make($payload, [
            'date'          => ['required', 'date'],
            'department_id' => ['required', 'exists:departments,id'],
            'data'          => ['required', 'array', 'min:1'],
            'data.*'        => ['array'],
            'note'          => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Validasi gagal',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $rows = $payload['data'];

        // header yang diharapkan (urutan sama dengan grid)
        $headers = [
            'heat_number',
            'item_code',
            'item_name',
            'weight_per_pc',
            'batch_qty',
            'aisi',
            'size',
            'line',
            'cust_name',
        ];

        // Deteksi apakah baris pertama adalah header, jika ya -> skip
        $first       = $rows[0] ?? [];
        $firstLower  = array_map(function ($v) {
            return strtolower(trim((string) $v));
        }, $first);

        $looksLikeHeader =
            in_array('heat_number', $firstLower, true) ||
            in_array('item_code', $firstLower, true);

        if ($looksLikeHeader) {
            array_shift($rows);
        }

        // Bersihkan rows jadi array asosiatif rapi & buang baris kosong
        $clean = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            // cek apakah baris ini punya setidaknya satu kolom berisi
            $hasValue = collect($row)->some(function ($v) {
                return trim((string) $v) !== '';
            });

            if (! $hasValue) {
                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $key) {
                $assoc[$key] = isset($row[$i])
                    ? trim((string) $row[$i])
                    : null;
            }

            $clean[] = $assoc;
        }

        if (empty($clean)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada baris data yang valid.',
            ], 422);
        }

        // Mulai transaksi DB: buat ImportSession + insert Batch
        DB::beginTransaction();

        try {
            // 1) Buat session import
            $session = ImportSession::create([
                'date'          => Carbon::parse($payload['date'])->toDateString(),
                'department_id' => $payload['department_id'],
                'created_by'    => auth()->id() ?? 1, // sesuaikan fallback-nya kalau perlu
                'note'          => $payload['note'] ?? null,
            ]);

            // 2) Insert baris-baris batch
            $seq               = 0;
            $inserted          = 0;

            // detail skip
            $skippedEmpty      = 0;
            $skippedDuplicate  = 0;

            foreach ($clean as $r) {
                $heat = trim((string) ($r['heat_number'] ?? ''));
                $code = trim((string) ($r['item_code'] ?? ''));

                // Kalau heat_number dan item_code kosong, skip
                if ($heat === '' && $code === '') {
                    $skippedEmpty++;
                    continue;
                }

                // Cek duplikat berdasarkan unique index (heat_number + item_code)
                $exists = Batch::where('heat_number', $heat)
                    ->where('item_code', $code)
                    ->exists();

                if ($exists) {
                    // sudah ada di DB / di transaksi ini, jangan insert lagi
                    $skippedDuplicate++;
                    continue;
                }

                $seq++;

                $batchCode   = $this->generateBatchCodeForSession($session->date, $seq);
                $itemName    = $r['item_name'] ?? null;
                $weightPerPc = $this->toFloat($r['weight_per_pc'] ?? null) ?? 0.0;
                $batchQty    = (int) ($r['batch_qty'] ?? 0);
                $aisi        = isset($r['aisi']) ? trim((string) $r['aisi']) : null;
                $size        = isset($r['size']) ? trim((string) $r['size']) : null;
                $line        = isset($r['line']) ? trim((string) $r['line']) : null;
                $custName    = isset($r['cust_name']) ? trim((string) $r['cust_name']) : null;

                Batch::create([
                    'heat_number'       => $heat,
                    'item_code'         => $code,
                    'item_name'         => $itemName ?: null,
                    'weight_per_pc'     => $weightPerPc,
                    'batch_qty'         => $batchQty,
                    'cast_date'         => $session->date,
                    'import_session_id' => $session->id,
                    'batch_code'        => $batchCode,
                    'aisi'              => $aisi ?: null,
                    'size'              => $size ?: null,
                    'line'              => $line ?: null,
                    'cust_name'         => $custName ?: null,
                ]);

                $inserted++;
            }

            // Kalau tidak ada satupun yang ter-insert, rollback & kirim error
            if ($inserted === 0) {
                DB::rollBack();

                return response()->json([
                    'status'            => 'error',
                    'message'           => 'Tidak ada baris batch yang bisa disimpan.',
                    'skipped_empty'     => $skippedEmpty,
                    'skipped_duplicate' => $skippedDuplicate,
                ], 422);
            }

            DB::commit();

            return response()->json([
                'status'            => 'ok',
                'message'           => 'Paste import berhasil disimpan.',
                'session_id'        => $session->id,
                'session_date'      => $session->date,
                'department_id'     => $session->department_id,
                'inserted'          => $inserted,
                'skipped_empty'     => $skippedEmpty,
                'skipped_duplicate' => $skippedDuplicate,
            ]);

        } catch (QueryException $e) {
            DB::rollBack();

            // Kalau masih kena 1062 (duplicate key) walau sudah dicek, beri pesan yang jelas
            if ($e->getCode() === '23000') {
                Log::error('Duplicate key saat import batch.', [
                    'msg'            => $e->getMessage(),
                    'file'           => $e->getFile(),
                    'line'           => $e->getLine(),
                    'trace'          => $e->getTraceAsString(),
                    'payload_sample' => array_slice($rows, 0, 5),
                ]);

                return response()->json([
                    'status'  => 'error',
                    'message' => 'Duplikat data batch (heat_number + item_code) terdeteksi di database.',
                    'error'   => config('app.debug') ? $e->getMessage() : null,
                ], 422);
            }

            // Error query lain
            Log::error('Gagal menyimpan data paste (QueryException).', [
                'msg'            => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => $e->getTraceAsString(),
                'payload_sample' => array_slice($rows, 0, 5),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan data paste.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Gagal menyimpan data paste.', [
                'msg'            => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
                'trace'          => $e->getTraceAsString(),
                'payload_sample' => array_slice($rows, 0, 5),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menyimpan data paste.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Generate batch_code per session tanggal + urutan.
     * Contoh: CR-20250101-01
     */
    protected function generateBatchCodeForSession(string $sessionDate, int $seq): string
    {
        $d   = Carbon::parse($sessionDate)->format('Ymd');
        $idx = str_pad($seq, 2, '0', STR_PAD_LEFT);

        return "CR-{$d}-{$idx}";
    }

    /**
     * Konversi nilai string ke float dengan membersihkan spasi, koma, dsb.
     * Mengembalikan null jika tidak numerik.
     */
    protected function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $clean = str_replace([' ', ','], ['', '.'], trim((string) $value));

        return is_numeric($clean) ? (float) $clean : null;
    }
}
