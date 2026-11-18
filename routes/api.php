<?php

use App\Http\Controllers\Api\ConnectionApiController as Api;
use App\Http\Middleware\ApiAuth;
use Illuminate\Support\Facades\Route;

/*
| The connection of a business with another system (SarafBook). Called at the address of the
| business: https://<business>/api/v1/..., with one of its keys in "Authorization: Bearer".
*/
Route::prefix('v1')->middleware([ApiAuth::class, 'throttle:120,1'])->group(function () {
    Route::get('business', [Api::class, 'business']);
    Route::get('rates', [Api::class, 'rates']);
    Route::get('accounts', [Api::class, 'accounts']);
    Route::post('accounts', [Api::class, 'storeAccount']);
    Route::get('accounts/{id}', [Api::class, 'account'])->whereNumber('id');
    Route::get('accounts/{id}/statement', [Api::class, 'statement'])->whereNumber('id');
    Route::post('money-in', [Api::class, 'moneyIn']);
    Route::post('money-out', [Api::class, 'moneyOut']);
    Route::get('papers/{reference}', [Api::class, 'paper'])->where('reference', '[^/]+');
    Route::post('papers/{reference}/cancel', [Api::class, 'cancel'])->where('reference', '[^/]+');
});
