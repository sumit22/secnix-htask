<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Controllers\AnalystController;
use App\Modules\Identity\Http\Middleware\ResolveRequester;
use App\Modules\Lookups\Http\Controllers\LookupController;
use App\Modules\Lookups\Http\Controllers\QuotaController;
use Illuminate\Support\Facades\Route;

// v1 contract: docs/api.md. No auth yet; the caller is identified by X-Analyst-Id.
Route::prefix('v1')->group(static function (): void {
    Route::get('analysts', [AnalystController::class, 'index']);

    Route::middleware(ResolveRequester::class)->group(static function (): void {
        Route::get('quota', [QuotaController::class, 'show']);
        Route::get('lookups', [LookupController::class, 'index']);
        Route::post('lookups', [LookupController::class, 'store']);
        Route::get('lookups/{id}', [LookupController::class, 'show']);
        Route::post('lookups/{id}/run', [LookupController::class, 'run']);
    });
});
