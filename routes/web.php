<?php

use App\Http\Controllers\Public\PublicSubmissionController;
use App\Http\Controllers\Spaces\SpaceController;
use App\Http\Controllers\Spaces\TestimonialController;
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
    Route::patch('/{space}/settings', [SpaceController::class, 'updateSettings'])
        ->name('settings.update');

    // Testimonial moderation (testimonial-inbox OpenSpec change). Each
    // testimonial row's URL includes both the parent space slug and the
    // testimonial id so the PK-scoping check inside the controller can
    // catch cross-tenant guesses as 404 rather than 403.
    Route::post('/{space}/inbox/{testimonial}/favorite', [TestimonialController::class, 'favorite'])
        ->name('inbox.favorite');
    Route::post('/{space}/inbox/{testimonial}/wall-of-love', [TestimonialController::class, 'wallOfLove'])
        ->name('inbox.wall-of-love');
    Route::post('/{space}/inbox/{testimonial}/hidden', [TestimonialController::class, 'hidden'])
        ->name('inbox.hidden');
    Route::patch('/{space}/inbox/{testimonial}', [TestimonialController::class, 'update'])
        ->name('inbox.update');
    Route::delete('/{space}/inbox/{testimonial}', [TestimonialController::class, 'destroy'])
        ->name('inbox.destroy');
});

// Public testimonial submission endpoint (OpenSpec: public-testimonial-submission).
// Group 1: route + placeholder controller. Subsequent groups add validation,
// limit checks, and atomic persistence.
Route::post('s/{public_id}/submissions', [PublicSubmissionController::class, 'store'])
    ->middleware('throttle:public-submissions')
    ->name('public.submissions.store');

// Public submission form page (OpenSpec: public-submission-form).
// Auth-free, unthrottled GET — the page itself is read-only and serves
// only as the form respondents fill out before hitting the POST above.
Route::get('s/{public_id}', [PublicSubmissionController::class, 'show'])
    ->name('public.submissions.show');

require __DIR__.'/settings.php';
