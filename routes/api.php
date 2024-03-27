<?php

use App\Http\Controllers\ContractController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:api'])->group(function () {

    Route::get('contract/recap/{service}', [ContractController::class, 'recap'])->name('contract.recap');
    Route::apiResource('contract', ContractController::class)->names('contract');

    Route::get('/user', function (Request $request) {
        return $request->user();
    })->name('user');
});
