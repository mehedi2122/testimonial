<?php

use App\Http\Controllers\Public\PublicSubmissionController;
use App\Http\Controllers\Spaces\SpaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->prefix('spaces')->name('spaces.')->group(function () {
    Route::get('/', [SpaceController::class, 'index'])->name('index');

    // Space CRUD (space-crud OpenSpec change). Static /create + POST / must
    // precede {space}-bound routes so the placeholder segment isn't
    // captured by route model binding.
    Route::get('/create', [SpaceController::class, 'create'])->name('create');
    Route::post('/', [SpaceController::class, 'store'])->name('store');
    Route::delete('/{space}', [SpaceController::class, 'destroy'])->name('destroy');

    Route::get('/{space}/dashboard', [SpaceController::class, 'dashboard'])
        ->name('dashboard');
    Route::get('/{space}/inbox', [SpaceController::class, 'inbox'])
        ->name('inbox');
    Route::get('/{space}/embed', [SpaceController::class, 'embed'])
        ->name('embed');
    Route::get('/{space}/settings', [SpaceController::class, 'settings'])
        ->name('settings');
});

// Public testimonial submission endpoint (OpenSpec: public-testimonial-submission).
// Group 1: route + placeholder controller. Subsequent groups add validation,
// limit checks, and atomic persistence.
Route::post('s/{public_id}/submissions', [PublicSubmissionController::class, 'store'])
    ->middleware('throttle:public-submissions')
    ->name('public.submissions.store');

require __DIR__.'/settings.php';
