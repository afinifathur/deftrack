<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request; use App\Models\DefectLine; use App\Models\Department;
use Symfony\Component\HttpFoundation\StreamedResponse;
class ReportController extends Controller{
  public function index(){ $departments=Department::where('is_active',1)->get(); return view('reports.index', compact('departments')); }
  public function exportCsv(Request $request){
    $from=new \DateTime($request->from); $to=new \DateTime($request->to);
    $diff=$from->diff($to)->days; if($diff>95) abort(422,'Rentang waktu maksimal 3 bulan.');
    $response=new StreamedResponse(function() use($from,$to){
      $out=fopen('php://output','w'); fputcsv($out,['date','department','heat_number','item_code','defect_type','qty_pcs','qty_kg']);
      \App\Models\DefectLine::with(['defect.department','batch','type'])
        ->whereHas('defect', fn($q)=>$q->whereBetween('date', [$from->format('Y-m-d'),$to->format('Y-m-d')]))
        ->chunk(1000,function($chunk) use($out){
          foreach($chunk as $l){ fputcsv($out,[optional($l->defect)->date, optional(optional($l->defect)->department)->name, optional($l->batch)->heat_number, optional($l->batch)->item_code, optional($l->type)->name, $l->qty_pcs, $l->qty_kg]); }
        });
      fclose($out);
    });
    $response->headers->set('Content-Type','text/csv');
    $response->headers->set('Content-Disposition','attachment; filename="deftrack_export.csv"');
    return $response;
  }
}