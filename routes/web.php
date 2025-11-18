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
| Root
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
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
| Dashboard (public to authenticated users)
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
    Route::prefix('api')->group(function () {
        Route::get('/heat',      [LookupController::class, 'heats'])->name('api.heat');
        Route::get('/item-info', [LookupController::class, 'itemInfo'])->name('api.itemInfo');
    });

    /*
    |--------------------------------------------------------------------------
    | Reports (auth)
    |--------------------------------------------------------------------------
    */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',            [ReportController::class, 'index'])->name('index');
        Route::get('/estimate',   [ReportController::class, 'estimate'])->name('estimate');
        Route::get('/export-csv', [ReportController::class, 'exportCsv'])->name('exportCsv');
        Route::get('/export-xlsx',[ReportController::class, 'exportXlsx'])->name('exportXlsx');
        Route::get('/export-pdf', [ReportController::class, 'exportPdf'])->name('exportPdf');
    });

    /*
    |--------------------------------------------------------------------------
    | Imports (CSV) - Auth only (for local/testing). In production consider role lock.
    |--------------------------------------------------------------------------
    */
    Route::prefix('imports')->name('imports.')->group(function () {
        Route::get('/',               [BatchImportController::class, 'index'])->name('index');
        Route::get('/create',         [BatchImportController::class, 'create'])->name('create');
        Route::post('/',              [BatchImportController::class, 'store'])->name('store');
        Route::get('/{importSession}',[BatchImportController::class, 'show'])->name('show');
        Route::get('/{importSession}/edit', [BatchImportController::class, 'edit'])->name('edit');
        Route::put('/{importSession}',[BatchImportController::class, 'update'])->name('update');
        Route::delete('/{importSession}',[BatchImportController::class, 'destroy'])->name('destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Defects (route order matters)
    |--------------------------------------------------------------------------
    |
    | Note:
    | - Static routes (create, recycle, etc.) MUST be declared before the dynamic
    |   parameter route '/defects/{defect}' to avoid 'create' or 'recycle' being
    |   interpreted as {defect}.
    | - We also add ->whereNumber('defect') on dynamic routes as extra protection.
    */

    // list (auth users) and create/store (restricted)
    Route::get('/defects', [DefectController::class, 'index'])->name('defects.index');

    // create/store: Admin QC & Kabag QC only
    Route::middleware([RoleMiddleware::class . ':admin_qc,kabag_qc'])->group(function () {
        Route::get('/defects/create', [DefectController::class, 'create'])->name('defects.create');
        Route::post('/defects', [DefectController::class, 'store'])->name('defects.store');
    });

    // Recycle & restore (static routes) — declare before {defect}
    Route::middleware([RoleMiddleware::class . ':kabag_qc,direktur,mr'])->group(function () {
        Route::get('/defects/recycle', [DefectController::class, 'recycle'])->name('defects.recycle');
        Route::post('/defects/{id}/restore', [DefectController::class, 'restore'])->name('defects.restore')->whereNumber('id');
    });

    // Public to authenticated users: show (BUT restrict dynamic param to numbers)
    Route::get('/defects/{defect}', [DefectController::class, 'show'])
        ->name('defects.show')
        ->whereNumber('defect');

    // Single-item actions (edit/update/destroy) — auth required; controller enforces finer-grained rules
    Route::middleware('auth')->group(function () {
        Route::get('/defects/{defect}/edit', [DefectController::class, 'edit'])->name('defects.edit')->whereNumber('defect');
        Route::put('/defects/{defect}', [DefectController::class, 'update'])->name('defects.update')->whereNumber('defect');
        Route::delete('/defects/{defect}', [DefectController::class, 'destroy'])->name('defects.destroy')->whereNumber('defect');
    });

    // submit / approvals: Kabag QC only (static / parameter routes placed with constraints)
    Route::middleware([RoleMiddleware::class . ':kabag_qc'])->group(function () {
        Route::post('/defects/{defect}/submit', [DefectController::class, 'submit'])->name('defects.submit')->whereNumber('defect');
        Route::post('/approvals/{defect}/approve', [ApprovalController::class, 'approve'])->name('approvals.approve')->whereNumber('defect');
        Route::post('/approvals/{defect}/reject', [ApprovalController::class, 'reject'])->name('approvals.reject')->whereNumber('defect');
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
    Route::post('/settings/departments', [SettingsController::class, 'storeDepartment'])->name('settings.departments.store');
    Route::patch('/settings/departments/{department}/toggle', [SettingsController::class, 'toggleDepartment'])->name('settings.departments.toggle');

    // Types
    Route::post('/settings/types', [SettingsController::class, 'storeType'])->name('settings.types.store');
});

/*
|--------------------------------------------------------------------------
| Fallback
|--------------------------------------------------------------------------
*/
Route::fallback(function () {
    return redirect()->route('login');
});
