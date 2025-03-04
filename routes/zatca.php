<?php

use Illuminate\Support\Facades\Route;
use KhaledHajSalem\ZatcaPhase2\Http\Controllers\ZatcaCallbackController;
use KhaledHajSalem\ZatcaPhase2\Http\Controllers\ZatcaDocumentController;

Route::middleware('api')->prefix('api/zatca')->group(function () {
    // Callback route for ZATCA
    Route::post('callback', [ZatcaCallbackController::class, 'handle'])->name('zatca.callback');

    // Document download routes
    Route::get('documents/{id}/pdf', [ZatcaDocumentController::class, 'downloadPdf'])->name('zatca.documents.pdf');
    Route::get('documents/{id}/qr', [ZatcaDocumentController::class, 'downloadQr'])->name('zatca.documents.qr');
    Route::get('documents/{id}/xml', [ZatcaDocumentController::class, 'downloadXml'])->name('zatca.documents.xml');
});