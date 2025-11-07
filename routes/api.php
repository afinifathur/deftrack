<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LookupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Endpoint untuk AJAX/lookup ringan. Pakai throttle & auth sesuai kebutuhan.
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/heat', [LookupController::class, 'heats'])->name('api.heat');
    Route::get('/item-info', [LookupController::class, 'itemInfo'])->name('api.itemInfo');
});
