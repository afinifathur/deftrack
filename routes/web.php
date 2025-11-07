<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{DashboardController,BatchImportController,DefectController,ApprovalController,ReportController,SettingsController};
use App\Http\Controllers\Api\LookupController;

Route::get('/', [DashboardController::class,'index'])->name('dashboard');
Route::get('/imports', [BatchImportController::class,'index'])->name('imports.index');
Route::get('/imports/create', [BatchImportController::class,'create'])->name('imports.create');
Route::post('/imports', [BatchImportController::class,'store'])->name('imports.store');
Route::delete('/imports/{importSession}', [BatchImportController::class,'destroy'])->name('imports.destroy');
Route::get('/defects', [DefectController::class,'index'])->name('defects.index');
Route::get('/defects/create', [DefectController::class,'create'])->name('defects.create');
Route::post('/defects', [DefectController::class,'store'])->name('defects.store');
Route::post('/defects/{defect}/submit', [DefectController::class,'submit'])->name('defects.submit');
Route::post('/approvals/{defect}/approve', [ApprovalController::class,'approve'])->name('approvals.approve');
Route::post('/approvals/{defect}/reject', [ApprovalController::class,'reject'])->name('approvals.reject');
Route::get('/reports', [ReportController::class,'index'])->name('reports.index');
Route::get('/reports/export-csv', [ReportController::class,'exportCsv'])->name('reports.exportCsv');
Route::get('/settings', [SettingsController::class,'index'])->name('settings.index');
Route::post('/settings/departments', [SettingsController::class,'storeDepartment'])->name('settings.departments.store');
Route::patch('/settings/departments/{department}/toggle', [SettingsController::class,'toggleDepartment'])->name('settings.departments.toggle');
Route::post('/settings/types', [SettingsController::class,'storeType'])->name('settings.types.store');
# routes/web.php
Route::get('/reports/export-xlsx', [ReportController::class,'exportXlsx'])->name('reports.exportXlsx');
Route::get('/reports/estimate', [ReportController::class,'estimate'])->name('reports.estimate');
// routes/web.php
Route::get('/reports/export-pdf', [ReportController::class,'exportPdf'])->name('reports.exportPdf');

Route::prefix('api')->group(function () {
    Route::get('/heat', [LookupController::class, 'heats'])->name('api.heat');
    Route::get('/item-info', [LookupController::class, 'itemInfo'])->name('api.itemInfo');
});
