<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LookupController extends Controller
{
    /**
     * GET /api/heats?prefix=H24&limit=20
     * Return top N heats matching prefix for UI autocomplete.
     */
    public function heats(Request $request)
    {
        $prefix = (string) $request->query('prefix', '');
        $limit  = (int) $request->query('limit', 20);

        // sanitasi & batasan
        $prefix = trim($prefix);
        $limit  = max(1, min($limit, 50)); // min 1, max 50

        if ($prefix === '') {
            return response()->json(['status' => 'ok', 'data' => []], 200);
        }

        // Cari matching heat_number yang diawali prefix (case-insensitive by DB collation)
        $rows = Batch::query()
            ->where('heat_number', 'like', $prefix . '%')
            ->orderByDesc('cast_date')
            ->limit($limit)
            ->get([
                'heat_number',
                'item_code',
                'item_name',
                'weight_per_pc',
                'batch_qty',
                'batch_code',
                'aisi',
                'size',
                'line',
                'cust_name',
            ]);

        return response()->json(['status' => 'ok', 'data' => $rows], 200);
    }

    /**
     * GET /api/item-info?heat=H240901 OR ?item_code=ABC123
     * Return a single batch matching heat or item_code for auto-fill, with additional fields.
     */
    public function itemInfo(Request $request)
    {
        $heat     = (string) $request->query('heat', '');
        $itemCode = (string) $request->query('item_code', '');

        if (trim($heat) === '' && trim($itemCode) === '') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter "heat" atau "item_code" required.',
            ], 422);
        }

        $query = Batch::query();

        if (trim($heat) !== '') {
            $query->where('heat_number', trim($heat));
        } else {
            $query->where('item_code', trim($itemCode));
        }

        $batch = $query->first([
            'heat_number',
            'item_code',
            'item_name',
            'weight_per_pc',
            'batch_qty',
            'batch_code',
            'aisi',
            'size',
            'line',
            'cust_name',
            'cast_date',
        ]);

        if (! $batch) {
            return response()->json([
                'status' => 'ok',
                'data'   => null,
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'heat_number'   => $batch->heat_number,
                'item_code'     => $batch->item_code,
                'item_name'     => $batch->item_name,
                'weight_per_pc' => $batch->weight_per_pc,
                'batch_qty'     => $batch->batch_qty,
                'batch_code'    => $batch->batch_code,
                'aisi'          => $batch->aisi,
                'size'          => $batch->size,
                'line'          => $batch->line,
                'cust_name'     => $batch->cust_name,
                'cast_date'     => optional($batch->cast_date)->toDateString() ?? null,
            ],
        ], 200);
    }

    /**
     * GET /api/next-batch-code?departemen=Cor%20Flange&date=2025-11-18
     * GET /api/next-batch-code?department_id=1&date=2025-11-18
     *
     * Menghasilkan kode batch dinamis per departemen dan tanggal.
     * Contoh respons:
     * {
     *   "status": "ok",
     *   "code": "CR-20251118-01",
     *   "prefix": "CR-20251118",
     *   "next": 1,
     *   "department": {
     *      "id": 1,
     *      "name": "Cor Flange"
     *   }
     * }
     */
    public function nextBatchCode(Request $request)
    {
        $departemen   = $request->query('departemen');      // nama departemen (optional)
        $departmentId = $request->query('department_id');   // id departemen (optional)
        $dateInput    = (string) $request->query('date', Carbon::now()->toDateString());

        // Validasi tanggal
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid date format. Use YYYY-MM-DD.',
            ], 422);
        }

        // Wajib ada salah satu: department_id atau departemen (name)
        if (
            ($departmentId === null || trim((string) $departmentId) === '') &&
            ($departemen === null || trim((string) $departemen) === '')
        ) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Parameter departemen (name) atau department_id wajib diisi.',
            ], 422);
        }

        /*
         * 1. Resolve departemen dari tabel departments
         *    - Kalau department_id ada → pakai itu
         *    - Kalau tidak → cari berdasarkan nama (kolom `name`)
         */
        $departmentRow = null;

        if ($departmentId !== null && is_numeric($departmentId)) {
            $departmentRow = DB::table('departments')
                ->where('id', (int) $departmentId)
                ->first();
        } elseif ($departemen !== null && trim((string) $departemen) !== '') {
            $departmentRow = DB::table('departments')
                ->where('name', trim((string) $departemen))
                ->first();
        }

        if (! $departmentRow) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Department tidak ditemukan.',
            ], 404);
        }

        $departmentId   = (int) $departmentRow->id;
        $departmentName = (string) ($departmentRow->name ?? '');

        /*
         * 2. Tentukan prefix kode:
         *    - Jika tabel departments punya kolom `code` dan terisi → pakai itu (dinamis dari DB)
         *    - Kalau tidak → generate dari nama (2 huruf pertama yang alphabet)
         */
        $prefixKey = null;

        if (Schema::hasColumn('departments', 'code') && ! empty($departmentRow->code)) {
            $prefixKey = strtoupper($departmentRow->code);
        } else {
            $cleanName = preg_replace('/[^A-Za-z]/', '', $departmentName);
            $prefixKey = strtoupper(Str::substr($cleanName, 0, 2) ?: 'XX');
        }

        /*
         * 3. Hitung jumlah batch existing untuk departemen & tanggal itu
         *    - Diasumsikan:
         *      * batches.import_session_id → import_sessions.id
         *      * import_sessions.department_id (ideal)
         *    - Kalau belum ada department_id di import_sessions:
         *      * fallback ke kolom `departemen` (string name) jika ada.
         */
        $dateStr = $date->toDateString();

        $query = DB::table('batches as b')
            ->join('import_sessions as s', 'b.import_session_id', '=', 's.id')
            ->whereDate('b.cast_date', $dateStr);

        if (Schema::hasColumn('import_sessions', 'department_id')) {
            // Skema ideal: simpan department_id di import_sessions
            $query->where('s.department_id', $departmentId);
        } elseif (Schema::hasColumn('import_sessions', 'departemen')) {
            // Fallback: simpan nama departemen di import_sessions
            $query->where('s.departemen', $departmentName);
        }

        $count = (int) $query->count();
        $next  = $count + 1;

        /*
         * 4. Bentuk kode: PREFIX-YYYYMMDD-XX
         */
        $datePart  = $date->format('Ymd');
        $indexPart = str_pad((string) $next, 2, '0', STR_PAD_LEFT);
        $code      = "{$prefixKey}-{$datePart}-{$indexPart}";

        return response()->json([
            'status'     => 'ok',
            'code'       => $code,
            'prefix'     => "{$prefixKey}-{$datePart}",
            'next'       => $next,
            'department' => [
                'id'   => $departmentId,
                'name' => $departmentName,
            ],
        ], 200);
    }
}
