<?php
namespace App\Http\Controllers;
use App\Models\DefectLine; use App\Models\Defect; use App\Models\Batch;
class DashboardController extends Controller{
  public function index(){
    $from=now()->subMonths(6)->startOfWeek(); $to=now()->endOfWeek();
    $defectPcs=DefectLine::whereBetween('created_at',[$from,$to])->sum('qty_pcs');
    $batchQty=Batch::whereBetween('cast_date',[$from,$to])->sum('batch_qty');
    $kpi=$batchQty>0?round(($defectPcs/$batchQty)*100,2):0;
    return view('dashboard', compact('kpi'));
  }
}