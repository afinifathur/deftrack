<?php
namespace App\Http\Controllers;
use App\Models\Department; use App\Models\DefectType; use Illuminate\Http\Request;
class SettingsController extends Controller{
  public function index(){ $departments=Department::orderBy('name')->get(); $types=DefectType::with('children')->get(); return view('settings.index', compact('departments','types'));}
  public function storeDepartment(Request $r){ $r->validate(['name'=>'required']); Department::create(['name'=>$r->name,'code'=>$r->code,'is_active'=>1]); return back()->with('status','Departemen ditambahkan');}
  public function toggleDepartment(Department $department){ $department->update(['is_active'=>!$department->is_active]); return back()->with('status','Departemen diubah');}
  public function storeType(Request $r){ $r->validate(['name'=>'required']); DefectType::create(['name'=>$r->name,'parent_id'=>$r->parent_id,'is_active'=>1]); return back()->with('status','Kategori/subkategori ditambahkan');}
}