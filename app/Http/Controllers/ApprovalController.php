<?php
namespace App\Http\Controllers;
use App\Models\Defect; use Illuminate\Http\Request;
class ApprovalController extends Controller{
  public function approve(Defect $defect){ $defect->update(['status'=>'approved','approved_by'=>auth()->id()??1,'rejected_reason'=>null]); return back()->with('status','Approved');}
  public function reject(Request $request, Defect $defect){ $request->validate(['reason'=>'required|string|min:3']); $defect->update(['status'=>'rejected','rejected_reason'=>$request->reason]); return back()->with('status','Rejected');}
}