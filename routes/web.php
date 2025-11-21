<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\RoleMiddleware;

// Controllers
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BatchImportController;
use App\Http\Controllers\BatchPasteImportController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\Api\LookupController;

/*
|--------------------------------------------------------------------------
| Root
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('root');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Dashboard (authenticated users)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

/*
|--------------------------------------------------------------------------
| Routes that require authentication
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | API: autocomplete / lookup
    |--------------------------------------------------------------------------
    */
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/heat',            [LookupController::class, 'heats'])->name('heat');
        Route::get('/item-info',       [LookupController::class, 'itemInfo'])->name('itemInfo');

        // Next batch code (auth-only)
        Route::get('/next-batch-code', [LookupController::class, 'nextBatchCode'])->name('nextBatchCode');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',            [ReportController::class, 'index'])->name('index');
        Route::get('/estimate',    [ReportController::class, 'estimate'])->name('estimate');
        Route::get('/export-csv',  [ReportController::class, 'exportCsv'])->name('exportCsv');
        Route::get('/export-xlsx', [ReportController::class, 'exportXlsx'])->name('exportXlsx');
        Route::get('/export-pdf',  [ReportController::class, 'exportPdf'])->name('exportPdf');
    });

    /*
    |--------------------------------------------------------------------------
    | Imports (CSV & Paste Spreadsheet)
    |--------------------------------------------------------------------------
    */
    Route::prefix('imports')->name('imports.')->group(function () {
        // List & CRUD import session (CSV-based)
        Route::get('/',       [BatchImportController::class, 'index'])->name('index');
        Route::get('/create', [BatchImportController::class, 'create'])->name('create');
        Route::post('/',      [BatchImportController::class, 'store'])->name('store');

        // Paste mode (Handsontable / spreadsheet-like input)
        Route::get('/paste',  [BatchPasteImportController::class, 'createPaste'])->name('paste');
        Route::post('/paste', [BatchPasteImportController::class, 'storePaste'])->name('paste.store');

        // Recycle Bin (Import Sessions)
        Route::get('/recycle-bin', [BatchImportController::class, 'recycle'])
            ->name('recycle');

        Route::post('/{importSession}/restore', [BatchImportController::class, 'restore'])
            ->name('restore')
            ->whereNumber('importSession');

        Route::delete('/{importSession}/force-delete', [BatchImportController::class, 'forceDelete'])
            ->name('forceDelete')
            ->whereNumber('importSession');

        // Export per import session
        Route::get('/{importSession}/export', [ImportController::class, 'export'])
            ->name('export')
            ->whereNumber('importSession');

        // Detail / edit / update / delete import session
        Route::get('/{importSession}',      [BatchImportController::class, 'show'])
            ->name('show')
            ->whereNumber('importSession');

        Route::get('/{importSession}/edit', [BatchImportController::class, 'edit'])
            ->name('edit')
            ->whereNumber('importSession');

        Route::put('/{importSession}',      [BatchImportController::class, 'update'])
            ->name('update')
            ->whereNumber('importSession');

        Route::delete('/{importSession}',   [BatchImportController::class, 'destroy'])
            ->name('destroy')
            ->whereNumber('importSession');
    });

    /*
    |--------------------------------------------------------------------------
    | Defects (route order matters: static routes before dynamic)
    |--------------------------------------------------------------------------
    */
    // List (authenticated users)
    Route::get('/defects', [DefectController::class, 'index'])->name('defects.index');

    // Create / store (Admin QC & Kabag QC)
    Route::middleware([RoleMiddleware::class . ':admin_qc,kabag_qc'])->group(function () {
        Route::get('/defects/create', [DefectController::class, 'create'])->name('defects.create');
        Route::post('/defects',       [DefectController::class, 'store'])->name('defects.store');
    });

    // Recycle & restore (static) — placed before dynamic {defect}
    Route::middleware([RoleMiddleware::class . ':kabag_qc,direktur,mr'])->group(function () {
        Route::get('/defects/recycle',       [DefectController::class, 'recycle'])->name('defects.recycle');
        Route::post('/defects/{id}/restore', [DefectController::class, 'restore'])
            ->name('defects.restore')
            ->whereNumber('id');
    });

    // Show (public to authenticated users) — restrict param to numbers
    Route::get('/defects/{defect}', [DefectController::class, 'show'])
        ->name('defects.show')
        ->whereNumber('defect');

    // Single-item actions (edit/update/destroy)
    Route::get('/defects/{defect}/edit', [DefectController::class, 'edit'])
        ->name('defects.edit')
        ->whereNumber('defect');

    Route::put('/defects/{defect}', [DefectController::class, 'update'])
        ->name('defects.update')
        ->whereNumber('defect');

    Route::delete('/defects/{defect}', [DefectController::class, 'destroy'])
        ->name('defects.destroy')
        ->whereNumber('defect');

    // Submit / approvals (Kabag QC only)
    Route::middleware([RoleMiddleware::class . ':kabag_qc'])->group(function () {
        Route::post('/defects/{defect}/submit', [DefectController::class, 'submit'])
            ->name('defects.submit')
            ->whereNumber('defect');

        Route::post('/approvals/{defect}/approve', [ApprovalController::class, 'approve'])
            ->name('approvals.approve')
            ->whereNumber('defect');

        Route::post('/approvals/{defect}/reject',  [ApprovalController::class, 'reject'])
            ->name('approvals.reject')
            ->whereNumber('defect');
    });
});

/*
|--------------------------------------------------------------------------
| Settings & Management (role-protected)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', RoleMiddleware::class . ':kabag_qc,direktur,mr,admin'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');

    // Departments
    Route::post('/settings/departments', [SettingsController::class, 'storeDepartment'])
        ->name('settings.departments.store');

    Route::patch('/settings/departments/{department}/toggle', [SettingsController::class, 'toggleDepartment'])
        ->name('settings.departments.toggle');

    // Types
    Route::post('/settings/types', [SettingsController::class, 'storeType'])
        ->name('settings.types.store');
});

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return redirect()->route('login');
});
