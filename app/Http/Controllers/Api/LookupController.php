<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\ImportSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LookupController extends Controller
{
    /**
     * GET /api/heats?prefix=H24&limit=20
     * Return top N heats matching prefix for UI autocomplete.
     */
    public function heats(Request $request)
    {
        $prefix = (string) $request->query('prefix', '');
        $limit = (int) $request->query('limit', 20);

        // sanitasi & batasan
        $prefix = trim($prefix);
        $limit = max(1, min($limit, 50)); // min 1, max 50

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
        $heat = (string) $request->query('heat', '');
        $itemCode = (string) $request->query('item_code', '');

        if (trim($heat) === '' && trim($itemCode) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter "heat" atau "item_code" required.'
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

        if (!$batch) {
            return response()->json([
                'status' => 'ok',
                'data' => null
            ], 200);
        }

        return response()->json([
            'status' => 'ok',
            'data' => [
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
                'cast_date'     => optional($batch->cast_date)->toString() ?? null,
            ]
        ], 200);
    }

    /**
     * GET /api/next-batch-code?departemen=Cor%20Flange&date=2025-11-18
     *
     * Accepts either:
     *  - departemen (string name), or
     *  - department_id (integer)
     *
     * Returns JSON:
     * { status: 'ok', code: 'CR-20251118-01', prefix: 'CR-20251118', next: 1 }
     */
    public function nextBatchCode(Request $request)
    {
        $departemen = $request->query('departemen', null);
        $departmentId = $request->query('department_id', null);
        $dateInput = (string) $request->query('date', Carbon::now()->toDateString());

        // validate date
        try {
            $date = Carbon::parse($dateInput)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => 'Invalid date format. Use YYYY-MM-DD.'], 422);
        }

        // require either departemen or department_id
        if (($departemen === null || trim((string)$departemen) === '') && ($departmentId === null || trim((string)$departmentId) === '')) {
            return response()->json(['status' => 'error', 'message' => 'Parameter departemen (name) or department_id is required.'], 422);
        }

        // mapping departemen -> code (extendable)
        $map = [
            'Cor Flange'         => 'CR',
            'BUBUT'              => 'BT',
            'BOR'                => 'BR',
            'Netto Potong'       => 'NT',
            'Gudang Jadi'        => 'GJ',
        ];

        // determine prefixKey
        $prefixKey = null;
        if ($departmentId !== null && is_numeric($departmentId)) {
            // try to lookup department name from import_sessions or departments table
            $name = DB::table('departments')->where('id', (int)$departmentId)->value('name');
            if ($name) {
                if (isset($map[$name])) {
                    $prefixKey = $map[$name];
                } else {
                    $clean = preg_replace('/[^A-Za-z]/', '', $name);
                    $prefixKey = strtoupper(Str::substr($clean, 0, 2)) ?: 'XX';
                }
            }
        }

        if ($prefixKey === null && $departemen !== null) {
            $depClean = trim((string)$departemen);
            if (isset($map[$depClean])) {
                $prefixKey = $map[$depClean];
            } else {
                $clean = preg_replace('/[^A-Za-z]/', '', $depClean);
                $prefixKey = strtoupper(Str::substr($clean, 0, 2)) ?: 'XX';
            }
        }

        // Count existing batches for the same departemen/date
        // Flexible where clause:
        // - If import_sessions stores department_id, use that
        // - If import_sessions stores departemen (name), use that
        $dateStr = $date->toDateString();

        $query = DB::table('batches as b')
            ->join('import_sessions as s', 'b.import_session_id', '=', 's.id')
            ->whereDate('b.cast_date', $dateStr);

        // try department_id first
        if ($departmentId !== null && is_numeric($departmentId)) {
            // if your import_sessions table has department_id column:
            if (SchemaHasColumn('import_sessions', 'department_id')) {
                $query->where('s.department_id', (int)$departmentId);
            } else {
                // fallback: try to match department name via departments table
                $query->where('s.departemen', '=', (string)$departemen);
            }
        } else {
            // use departemen name (assume import_sessions.departemen exists)
            if (SchemaHasColumn('import_sessions', 'departemen')) {
                $query->where('s.departemen', '=', (string)$departemen);
            } else {
                // fallback: try to resolve department name to id via departments table
                $deptId = DB::table('departments')->where('name', (string)$departemen)->value('id');
                if ($deptId) {
                    if (SchemaHasColumn('import_sessions', 'department_id')) {
                        $query->where('s.department_id', $deptId);
                    } else {
                        // no reliable column -> try matching by department name anyway
                        $query->where('s.departemen', '=', (string)$departemen);
                    }
                } else {
                    // nothing matched; count will be zero (safe fallback)
                    $query->whereRaw('1 = 0');
                }
            }
        }

        $count = (int) $query->count();
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

/**
 * Small helper to detect if a DB table has a column.
 * Keep it near controller for convenience (or move to helper/util).
 */
if (! function_exists('SchemaHasColumn')) {
    function SchemaHasColumn(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
