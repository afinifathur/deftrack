<?php
namespace App\Http\Controllers;
use App\Models\Batch; use App\Models\ImportSession; use App\Models\Department;
use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
class BatchImportController extends Controller{
  public function index(){ $sessions=ImportSession::with('department')->orderByDesc('date')->paginate(20); return view('imports.index', compact('sessions'));}
  public function create(){ $departments=Department::where('is_active',1)->get(); return view('imports.create', compact('departments'));}
  public function store(Request $request){
    $request->validate(['date'=>'required|date','department_id'=>'required|exists:departments,id','file'=>'required|file|mimes:csv,txt']);
    $session=ImportSession::create(['date'=>$request->date,'department_id'=>$request->department_id,'created_by'=>auth()->id()??1,'note'=>$request->note]);
    $csv=array_map('str_getcsv', file($request->file('file')->getRealPath()));
    $header=array_map('trim', array_map('strtolower', $csv[0]??[])); $map=array_flip($header);
    $ins=0;$upd=0;
    DB::transaction(function() use($csv,$map,$session,&$ins,&$upd){
      foreach(array_slice($csv,1) as $r){
        if(count($r)<5) continue;
        $hn=trim($r[$map['heat_number']]??''); $ic=trim($r[$map['item_code']]??'');
        if(!$hn||!$ic) continue;
        $b=Batch::firstOrNew(['heat_number'=>$hn,'item_code'=>$ic]);
        $b->item_name=trim($r[$map['item_name']]??''); $b->weight_per_pc=(float)($r[$map['weight_per_pc']]??0);
        $b->batch_qty=(int)($r[$map['batch_qty']]??0); $b->cast_date=now()->toDateString(); $b->import_session_id=$session->id;
        if($b->exists) $upd++; else $ins++; $b->save();
      }
    });
    return redirect()->route('imports.index')->with('status',"Import selesai. Insert: $ins, Update: $upd");
  }
  public function destroy(ImportSession $importSession){ $importSession->batches()->delete(); $importSession->delete(); return back()->with('status','Import session dihapus.');}
}