<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LookupController extends Controller
{
    /**
     * GET /api/heat?prefix=H24
     * Return top 20 heats matching prefix for UI autocomplete.
     */
    public function heats(Request $request)
    {
        $prefix = (string) $request->query('prefix', '');

        // quick exit for empty input
        if (strlen(trim($prefix)) < 1) {
            return response()->json(['data' => []], 200);
        }

        $rows = Batch::query()
            ->where('heat_number', 'like', $prefix . '%')
            ->orderByDesc('cast_date')
            ->limit(20)
            ->get([
                'heat_number',
                'item_code',
                'item_name',
                'weight_per_pc',
                'batch_qty',
            ]);

        return response()->json(['data' => $rows], 200);
    }

    /**
     * GET /api/item-info?heat=H240901&item=FLG-2IN-150
     * Return a single batch matching heat + item for auto-fill.
     */
    public function itemInfo(Request $request)
    {
        $heat = (string) $request->query('heat', '');
        $item = (string) $request->query('item', '');

        if ($heat === '' || $item === '') {
            return response()->json([
                'data' => null,
                'message' => 'Both heat and item are required.'
            ], 422);
        }

        $batch = Batch::query()
            ->where('heat_number', $heat)
            ->where('item_code', $item)
            ->first([
                'heat_number',
                'item_code',
                'item_name',
                'weight_per_pc',
                'batch_qty',
                'cast_date',
            ]);

        return response()->json(['data' => $batch], 200);
    }

    /**
     * GET /api/next-batch-code?departemen=Cor%20Flange&date=2025-11-18
     *
     * Response:
     * {
     *   "status": "ok",
     *   "code": "CR-20251118-01",
     *   "prefix": "CR-20251118",
     *   "next": 1
     * }
     *
     * Notes:
     * - Expects `departemen` (string). `date` optional (YYYY-MM-DD), defaults to today.
     * - Mapping table for departemen -> short code can be extended.
     * - Query counts existing batches for that departemen and date.
     *   If your import_sessions table uses department_id instead of departemen name,
     *   replace the where clause accordingly (comment shown below).
     */
    public function nextBatchCode(Request $request)
    {
        // validate inputs (basic)
        $departemen = (string) $request->query('departemen', '');
        $dateInput = (string) $request->query('date', Carbon::now()->toDateString());

        if (trim($departemen) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'departemen parameter is required'
            ], 422);
        }

        // parse date, return error if invalid
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid date format. Use YYYY-MM-DD.'
            ], 422);
        }

        // mapping departemen -> short code (extend as needed)
        $map = [
            'Cor Flange'         => 'CR',
            'BUBUT'              => 'BT',
            'BOR'                => 'BR',
            'Netto Potong'       => 'NT',
            'Gudang Jadi'        => 'GJ',
            // add more mappings here
        ];

        // derive prefix: prefer explicit mapping, otherwise take first 2 letters from cleaned name
        if (isset($map[$departemen])) {
            $prefixKey = $map[$departemen];
        } else {
            // remove non-letters, make uppercase, take up to 2 chars
            $clean = preg_replace('/[^A-Za-z]/', '', $departemen);
            $prefixKey = strtoupper(Str::substr($clean, 0, 2)) ?: 'XX';
        }

        // count existing batches for that departemen & date
        // NOTE: This assumes import_sessions table has a `departemen` VARCHAR column storing the name.
        // If you store a department id instead, change the where clause to match (e.g. where('s.department_id', $deptId))
        $count = DB::table('batches as b')
            ->join('import_sessions as s', 'b.import_session_id', '=', 's.id')
            ->where('s.departemen', $departemen)
            ->whereDate('b.cast_date', $date->toDateString())
            ->count();

        $next = $count + 1;
        $datePart = $date->format('Ymd');
        $indexPart = str_pad($next, 2, '0', STR_PAD_LEFT);
        $code = "{$prefixKey}-{$datePart}-{$indexPart}";

        return response()->json([
            'status' => 'ok',
            'code'   => $code,
            'prefix' => "{$prefixKey}-{$datePart}",
            'next'   => $next,
        ], 200);
    }
}
