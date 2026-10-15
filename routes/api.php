<?php

use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ModelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Read-only model API
|--------------------------------------------------------------------------
|
| Bearer-token authenticated (see AuthenticateApiToken, ApiToken), GET-only
| by construction — there is no write path here at all. Meant for direct
| querying (by an assistant, a script) of this single-tenant site's data,
| separate from the normal browser session/2FA-guarded web routes.
|
*/

Route::middleware(['auth.api_token', 'throttle:120,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('models', [ModelController::class, 'listAvailable'])->name('api.models.index');
        Route::get('models/{model}', [ModelController::class, 'index'])->name('api.models.list');
        Route::get('models/{model}/{id}', [ModelController::class, 'show'])->name('api.models.show');

        Route::get('media', [MediaController::class, 'index'])->name('api.media.index');
        Route::get('media/{id}', [MediaController::class, 'show'])->name('api.media.show');
    });
