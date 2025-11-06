<?php
namespace App\Http\Controllers;
use App\Models\Defect; use App\Models\DefectLine; use App\Models\Batch; use App\Models\Department; use App\Models\DefectType;
use Illuminate\Http\Request; use Illuminate\Support\Facades\DB;
class DefectController extends Controller{
  public function index(Request $r){
    $q=Defect::with('department')->orderByDesc('date');
    if($r->filled('department_id')) $q->where('department_id',$r->department_id);
    $defects=$q->paginate(20);
    $departments=Department::where('is_active',1)->get();
    return view('defects.index', compact('defects','departments'));
  }
  public function create(){
    $departments=Department::where('is_active',1)->get();
    $types=DefectType::whereNull('parent_id')->with('children')->get();
    return view('defects.create', compact('departments','types'));
  }
  public function store(Request $request){
    $request->validate([
      'date'=>'required|date',
      'department_id'=>'required|exists:departments,id',
      'lines'=>'required|array|min:1',
      'lines.*.heat_number'=>'required',
      'lines.*.item_code'=>'required',
      'lines.*.defect_type_id'=>'required|exists:defect_types,id',
    ]);
    DB::transaction(function() use($request){
      $defect=Defect::create([
        'date'=>$request->date,
        'department_id'=>$request->department_id,
        'status'=>'draft',
        'submitted_by'=>auth()->id()??1,
      ]);
      foreach($request->lines as $line){
        $batch=Batch::where('heat_number',$line['heat_number'])->where('item_code',$line['item_code'])->first();
        if(!$batch) continue;
        $current=DefectLine::where('batch_id',$batch->id)->sum('qty_pcs');
        $incoming=(int)($line['qty_pcs'] ?? 0);
        if($current + $incoming > $batch->batch_qty){
          abort(422, 'Total defect qty melebihi batch_qty untuk heat '.$batch->heat_number.' / '.$batch->item_code);
        }
        DefectLine::create([
          'defect_id'=>$defect->id,
          'batch_id'=>$batch->id,
          'defect_type_id'=>$line['defect_type_id'],
          'subtype_id'=>$line['subtype_id'] ?? null,
          'qty_pcs'=>$incoming,
          'qty_kg'=>$line['qty_kg'] ?? 0,
        ]);
      }
    });
    return redirect()->route('defects.index')->with('status','Defect disimpan sebagai draft.');
  }
  public function submit(Defect $defect){
    $defect->update(['status'=>'submitted']);
    return back()->with('status','Defect submitted ke Kabag QC.');
  }
}