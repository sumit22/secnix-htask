<?php

declare(strict_types=1);

// API routes (prefixed with /api). No authentication by design: the analyst is identified by an id in the request.

use App\Modules\Identity\Http\Controllers\AnalystController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(static function (): void {
    Route::get('analysts', [AnalystController::class, 'index']);
});
