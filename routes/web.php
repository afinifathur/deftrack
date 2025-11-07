<?php

use Illuminate\Support\Facades\Route;

// Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\{
    DashboardController,
    BatchImportController,
    DefectController,
    ApprovalController,
    ReportController,
    SettingsController
};
use App\Http\Controllers\Api\LookupController;

// Middleware (class-string hotfix)
use App\Http\Middleware\RoleMiddleware;

/*
|--------------------------------------------------------------------------
| ROOT: redirect aman (login → dashboard)
|--------------------------------------------------------------------------
*/
// Akses ke root app
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('root');

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
// Dashboard di /dashboard (boleh diakses publik atau pindahkan ke auth jika perlu)
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Protected (auth)
|--------------------------------------------------------------------------
*/

// ---- Routes yang cukup auth saja (semua role login boleh)
Route::middleware('auth')->group(function () {
    // Defects: daftar
    Route::get('/defects', [DefectController::class, 'index'])->name('defects.index');

    // Reports
    Route::get('/reports',            [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/estimate',   [ReportController::class, 'estimate'])->name('reports.estimate'); // tetap ada
    Route::get('/reports/export-csv', [ReportController::class, 'exportCsv'])->name('reports.exportCsv');
    Route::get('/reports/export-xlsx',[ReportController::class, 'exportXlsx'])->name('reports.exportXlsx');
    Route::get('/reports/export-pdf', [ReportController::class, 'exportPdf'])->name('reports.exportPdf');

    // API (autocomplete / lookup)
    Route::prefix('api')->group(function () {
        Route::get('/heat',      [LookupController::class, 'heats'])->name('api.heat');
        Route::get('/item-info', [LookupController::class, 'itemInfo'])->name('api.itemInfo');
    });
});

// ---- Imports & Settings: hanya Kabag QC + Direktur
Route::middleware([
    'auth',
    RoleMiddleware::class . ':kabag_qc,direktur',
])->group(function () {
    // Imports
    Route::get('/imports',               [BatchImportController::class, 'index'])->name('imports.index');
    Route::get('/imports/create',        [BatchImportController::class, 'create'])->name('imports.create');
    Route::post('/imports',              [BatchImportController::class, 'store'])->name('imports.store');
    Route::delete('/imports/{importSession}', [BatchImportController::class, 'destroy'])->name('imports.destroy');

    // Settings
    Route::get('/settings',                         [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/departments',            [SettingsController::class, 'storeDepartment'])->name('settings.departments.store');
    Route::patch('/settings/departments/{department}/toggle', [SettingsController::class, 'toggleDepartment'])->name('settings.departments.toggle');
    Route::post('/settings/types',                  [SettingsController::class, 'storeType'])->name('settings.types.store');
});

// ---- Defects input: Admin QC & Kabag QC
Route::middleware([
    'auth',
    RoleMiddleware::class . ':admin_qc,kabag_qc',
])->group(function () {
    Route::get('/defects/create', [DefectController::class, 'create'])->name('defects.create');
    Route::post('/defects',       [DefectController::class, 'store'])->name('defects.store');
});

// ---- Submit/Approve/Reject: Kabag QC
Route::middleware([
    'auth',
    RoleMiddleware::class . ':kabag_qc',
])->group(function () {
    Route::post('/defects/{defect}/submit',   [DefectController::class, 'submit'])->name('defects.submit');
    Route::post('/approvals/{defect}/approve',[ApprovalController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{defect}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject');
});
Route::fallback(function () {
    return redirect()->route('login');
});