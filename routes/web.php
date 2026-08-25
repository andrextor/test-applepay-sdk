<?php

use App\Http\Controllers\ApplePayButtonController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\MerchantValidationController;
use App\Http\Controllers\MockTokenController;
use App\Http\Controllers\RealTokenController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RealTokenController::class, 'show'])->name('real.show');
Route::post('/', [RealTokenController::class, 'decrypt'])->name('real.decrypt');

Route::get('/mock', [MockTokenController::class, 'show'])->name('mock.show');
Route::post('/mock', [MockTokenController::class, 'decrypt'])->name('mock.decrypt');

Route::get('/merchant-validation', [MerchantValidationController::class, 'show'])->name('merchant.show');
Route::post('/merchant-validation', [MerchantValidationController::class, 'validateMerchant'])->name('merchant.validate');

Route::get('/pay', [ApplePayButtonController::class, 'show'])->name('button.show');
Route::post('/pay/validate-merchant', [ApplePayButtonController::class, 'validateMerchant'])->name('button.validate');
Route::post('/pay/process', [ApplePayButtonController::class, 'process'])->name('button.process');

Route::get('/config', [ConfigController::class, 'show'])->name('config.show');
