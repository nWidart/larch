<?php

use Illuminate\Support\Facades\Route;
use Wired\Billing\Http\RefundController;

Route::post('refunds', [RefundController::class, 'store'])->name('refunds.store');
Route::get('refunds/{id}', [RefundController::class, 'show'])->middleware('auth:sanctum')->name('refunds.show');
Route::get('health', fn () => 'ok');

Route::middleware('admin')->group(function () {
    Route::get('webhooks/stripe', fn () => 'ok');
});
