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
     * GET /api/departments/{department}/categories
     */
    public function categories(int $departmentId): JsonResponse
    {
        try {
            // Jika pakai pivot category_department
            $categories = Category::whereHas('departments', function ($query) use ($departmentId) {
                    $query->where('departments.id', $departmentId);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'tag']);

            return response()->json([
                'status' => 'ok',
                'data'   => $categories,
            ]);
        } catch (\Throwable $e) {
            Log::error(
                "API departments.categories error for department {$departmentId}: " . $e->getMessage(),
                [
                    'department_id' => $departmentId,
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
