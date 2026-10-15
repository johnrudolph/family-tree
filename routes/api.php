<?php

use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\ModelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Model API
|--------------------------------------------------------------------------
|
| Bearer-token authenticated (see AuthenticateApiToken, ApiToken), separate
| from the normal browser session/2FA-guarded web routes. Meant for direct
| querying — and, for the narrow set of models config/api.php marks
| `writable`, correcting/adding records — by an assistant or script. There
| is no destroy route anywhere; store/update 403 for any non-writable model.
|
*/

Route::middleware(['auth.api_token', 'throttle:120,1'])
    ->prefix('v1')
    ->group(function () {
        Route::get('models', [ModelController::class, 'listAvailable'])->name('api.models.index');
        Route::get('models/{model}', [ModelController::class, 'index'])->name('api.models.list');
        Route::post('models/{model}', [ModelController::class, 'store'])->name('api.models.store');
        Route::get('models/{model}/{id}', [ModelController::class, 'show'])->name('api.models.show');
        Route::patch('models/{model}/{id}', [ModelController::class, 'update'])->name('api.models.update');

        Route::get('media', [MediaController::class, 'index'])->name('api.media.index');
        Route::get('media/{id}', [MediaController::class, 'show'])->name('api.media.show');
    });
