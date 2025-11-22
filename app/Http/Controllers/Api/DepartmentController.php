<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    public function categories(int $department): JsonResponse
    {
        try {
            $dept = Department::find($department);
            if (!$dept) {
                return response()->json(['status'=>'ok','data'=>[]]);
            }

            // ambil kategori yang khusus untuk departemen + kategori global (department_id NULL)
            // jika kamu ingin: global = categories where department_id IS NULL
            // dan juga kategori yang ter-assign ke department via pivot
            $pivot = $dept->categories()->select(['categories.id','categories.name','categories.tag'])->get();

            // ambil aussi kategori global (department_id IS NULL)
            $global = \App\Models\Category::whereNull('department_id')
                ->select(['id','name','tag'])
                ->get();

            // gabungkan + unique by id
            $merged = $pivot->concat($global)->unique('id')->values();

            return response()->json(['status'=>'ok','data'=>$merged]);
        } catch (\Throwable $e) {
            Log::error("API departments.categories error for department {$department}: ".$e->getMessage(), ['trace'=>$e->getTraceAsString()]);
            return response()->json(['status'=>'error','message'=>'Terjadi kesalahan pada server'],500);
        }
    }
}
