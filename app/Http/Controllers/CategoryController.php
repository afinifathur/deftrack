<?php
namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Department;
use Illuminate\Http\Request;


class CategoryController extends Controller
{
// API for defects/create
public function byDepartment($deptId)
{
$cats = Category::whereHas('departments', function($q) use ($deptId) {
$q->where('department_id', $deptId);
})->orderBy('name')->get(['id','name','tag']);


return response()->json(['data' => $cats]);
}


// Settings index (basic)
public function index()
{
$categories = Category::with('departments')->orderBy('name')->get();
$departments = Department::orderBy('name')->get();
return view('settings.categories.index', compact('categories','departments'));
}


public function store(Request $r)
{
$r->validate(['name' => 'required|string|unique:categories,name']);
$cat = Category::create(['name' => $r->name, 'tag' => $r->tag]);
if ($r->departments) $cat->departments()->sync($r->departments);
return back()->with('success','Kategori ditambahkan');
}


public function update(Request $r, Category $category)
{
$r->validate(['name'=>'required|string|unique:categories,name,'.$category->id]);
$category->update(['name'=>$r->name,'tag'=>$r->tag]);
$category->departments()->sync($r->departments ?? []);
return back()->with('success','Kategori diperbarui');
}


public function destroy(Category $category)
{
$category->delete();
return back()->with('status','Kategori dihapus');
}
}