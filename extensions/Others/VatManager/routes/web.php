<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\VatManager\Http\Controllers\VatManagerController;

Route::middleware(['web', 'auth'])->prefix('admin/extensions/vat-manager')->group(function () {
    Route::get('/', [VatManagerController::class, 'index'])->name('extensions.vat-manager.index');
    Route::post('/', [VatManagerController::class, 'update'])->name('extensions.vat-manager.update');
});
