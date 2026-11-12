<?php

use Facilo\AltLingo\Http\Controllers\CsvController;
use Facilo\AltLingo\Http\Controllers\GridController;
use Facilo\AltLingo\Http\Controllers\TranslateController;
use Illuminate\Support\Facades\Route;

Route::prefix('altlingo')->name('altlingo.')->group(function () {
    Route::get('/', [GridController::class, 'index'])->name('index');
    Route::get('rows', [GridController::class, 'rows'])->name('rows');
    Route::post('cell', [GridController::class, 'cell'])->name('cell');
    Route::post('bulk', [GridController::class, 'bulk'])->name('bulk');
    Route::post('translate', [TranslateController::class, 'start'])->name('translate');
    Route::post('translate/batch', [TranslateController::class, 'batch'])->name('translate-batch');
    Route::get('translate/progress/{run}', [TranslateController::class, 'progress'])->name('translate-progress');
    Route::post('translate/image/{image}', [TranslateController::class, 'one'])->name('translate-one');
    Route::get('export', [CsvController::class, 'export'])->name('export');
    Route::post('import/preview', [CsvController::class, 'preview'])->name('import-preview');
    Route::post('import/apply', [CsvController::class, 'apply'])->name('import-apply');
});
