<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\DepartmentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Semua endpoint di file ini otomatis diprefix dengan "/api" dan memakai
| middleware group "api" (lihat RouteServiceProvider).
|
| Contoh URL:
|   GET /api/heat
|   GET /api/item-info
|   GET /api/departments/{department}/categories
|--------------------------------------------------------------------------
*/

// Lookup / autocomplete
Route::get('heat', [LookupController::class, 'heats'])
    ->name('api.heat');

Route::get('item-info', [LookupController::class, 'itemInfo'])
    ->name('api.itemInfo');

// Departments -> Categories (dipakai dropdown kategori di defects)
Route::get('departments/{department}/categories', [DepartmentController::class, 'categories'])
    ->name('api.departments.categories')
    ->whereNumber('department');
