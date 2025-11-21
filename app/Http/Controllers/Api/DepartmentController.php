<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    /**
     * Ambil kategori berdasarkan ID departemen.
     *
     * Route: GET /api/departments/{department}/categories
     *
     * @param  int  $department  ID departemen
     */
    public function categories(int $department): JsonResponse
    {
        try {
            // Jika tabel categories punya kolom department_id
            // dan ingin menampilkan:
            // - kategori khusus departemen tsb (department_id = $department)
            // - kategori global (department_id = null)
            $categories = Category::query()
                ->where(function ($q) use ($department) {
                    $q->where('department_id', $department)
                      ->orWhereNull('department_id');
                })
                ->orderBy('name')
                ->get(['id', 'name', 'tag']);

            return response()->json([
                'status' => 'ok',
                'data'   => $categories,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                "API departments.categories error for department {$department}: {$e->getMessage()}",
                [
                    'department_id' => $department,
                    'trace'         => $e->getTraceAsString(),
                ]
            );

            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan pada server',
            ], 500);
        }
    }
}
