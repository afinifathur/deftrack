<?php
// app/Http/Controllers/Api/LookupController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * GET /api/heat?prefix=H24  -> top 20 heat_number (LIKE 'prefix%')
     * Return minimal data for UI suggestions.
     */
    public function heats(Request $request)
    {
        $prefix = (string) $request->query('prefix', '');
        if (strlen($prefix) < 1) {
            return response()->json(['data' => []]);
        }

        // LIKE prefix + index heat_number => cepat
        $rows = Batch::query()
            ->where('heat_number', 'like', $prefix.'%')
            ->orderByDesc('cast_date')
            ->limit(20)
            ->get(['heat_number','item_code','item_name','weight_per_pc','batch_qty']);

        return response()->json(['data' => $rows]);
    }

    /**
     * GET /api/item-info?heat=H240901&item=FLG-2IN-150
     * Return single batch to auto fill detail (optional helper).
     */
    public function itemInfo(Request $request)
    {
        $heat = (string) $request->query('heat', '');
        $item = (string) $request->query('item', '');

        if ($heat === '' || $item === '') {
            return response()->json(['data' => null], 400);
        }

        $batch = Batch::where('heat_number',$heat)
            ->where('item_code',$item)
            ->first(['heat_number','item_code','item_name','weight_per_pc','batch_qty','cast_date']);

        return response()->json(['data' => $batch]);
    }
}
