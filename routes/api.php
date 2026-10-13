<?php

use App\Http\Controllers\Worker\WorkerController;
use App\Http\Middleware\EnsureWorkerToken;
use Illuminate\Support\Facades\Route;

Route::middleware(EnsureWorkerToken::class)->prefix('worker')->name('worker.')->group(function () {
    Route::post('claim', [WorkerController::class, 'claim'])->name('claim');
    Route::post('runs/{run}/log', [WorkerController::class, 'log'])->name('log');
    Route::post('runs/{run}/assignments', [WorkerController::class, 'assignment'])->name('assignment');
    Route::post('runs/{run}/finish', [WorkerController::class, 'finish'])->name('finish');
});
