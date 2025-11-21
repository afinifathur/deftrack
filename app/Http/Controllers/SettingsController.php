<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Department;
use App\Models\DefectType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Halaman utama pengaturan.
     */
    public function index()
    {
        $departments = Department::orderBy('name')->get();
        $types       = DefectType::with('children')->get();

        return view('settings.index', compact('departments', 'types'));
    }

    /**
     * Simpan departemen baru.
     */
    public function storeDepartment(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        Department::create([
            'name'      => $validated['name'],
            'code'      => $validated['code'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', 'Departemen ditambahkan');
    }

    /**
     * Toggle status aktif/non-aktif departemen.
     */
    public function toggleDepartment(Department $department)
    {
        $department->update([
            'is_active' => ! (bool) $department->is_active,
        ]);

        return back()->with('status', 'Departemen diubah');
    }

    /**
     * Simpan type / kategori / subkategori baru.
     */
    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'exists:defect_types,id'],
        ]);

        DefectType::create([
            'name'      => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('status', 'Kategori/subkategori ditambahkan');
    }

    /**
     * API: Ambil daftar kategori berdasarkan departemen.
     *
     * Digunakan oleh route:
     * GET /api/departments/{department}/categories
     */
    public function departmentCategories($departmentId): JsonResponse
    {
        // Asumsi: Category punya kolom department_id
        $categories = Category::where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Fallback ke kategori global jika tidak ada kategori khusus per department
        if ($categories->isEmpty()) {
            $categories = Category::whereNull('department_id')
                ->orderBy('name')
                ->get(['id', 'name']);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => $categories,
        ]);
    }
}
