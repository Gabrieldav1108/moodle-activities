<?php

use App\Http\Controllers\AssignmentDownloadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('automation', 'pages::automation')->name('automation.edit');

    Route::livewire('runs', 'pages::runs.index')->name('runs.index');
    Route::livewire('runs/{run}', 'pages::runs.show')->name('runs.show');

    Route::livewire('assignments', 'pages::assignments.index')->name('assignments.index');
    Route::livewire('assignments/{assignment}', 'pages::assignments.show')->name('assignments.show');
    Route::get('assignments/{assignment}/files/{file}', [AssignmentDownloadController::class, 'file'])->name('assignments.files.download');
    Route::get('assignments/{assignment}/draft.zip', [AssignmentDownloadController::class, 'zip'])->name('assignments.zip');
});

require __DIR__.'/settings.php';
