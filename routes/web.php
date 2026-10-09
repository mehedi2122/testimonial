<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Embed\EmbedWidgetController;
use App\Http\Controllers\Public\PublicSubmissionController;
use App\Http\Controllers\Public\PublicWallController;
use App\Http\Controllers\Public\TestimonialPhotoController;
use App\Http\Controllers\Spaces\SpaceController;
use App\Http\Controllers\Spaces\TestimonialController;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->prefix('spaces')->name('spaces.')->group(function () {
    Route::get('/', [SpaceController::class, 'index'])->name('index');

    // Space CRUD (space-crud OpenSpec change). Static /create + POST / must
    // precede {space}-bound routes so the placeholder segment isn't
    // captured by route model binding.
    Route::get('/create', [SpaceController::class, 'create'])->name('create');
    Route::post('/', [SpaceController::class, 'store'])->name('store');
    Route::delete('/{space}', [SpaceController::class, 'destroy'])->name('destroy');

    Route::get('/{space}/created', [SpaceController::class, 'created'])
        ->name('created');
    Route::get('/{space}/dashboard', [SpaceController::class, 'dashboard'])
        ->name('dashboard');
    Route::get('/{space}/inbox', [SpaceController::class, 'inbox'])
        ->name('inbox');
    Route::get('/{space}/embed', [SpaceController::class, 'embed'])
        ->name('embed');
    Route::patch('/{space}/embed', [SpaceController::class, 'updateEmbed'])
        ->name('embed.update');
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

// Public embed widget (OpenSpec: embed-widget). The loader script
// /embed.js is a static file served by EmbedWidgetController::loader().
// /embed/{public_id} is the iframe document the loader mounts. No
// auth, no throttle — a missing public_id is a 404, not a redirect.
Route::get('embed.js', [EmbedWidgetController::class, 'loader'])
    ->name('embed.loader');
Route::get('embed/{publicId}', [EmbedWidgetController::class, 'frame'])
    ->name('embed.frame')
    ->where('publicId', '[A-Za-z0-9]+');

// Profile photos (OpenSpec: public-submission-fixes). Private disk;
// visibility checked per request (owner, or public testimonial with the
// photo field shown on embeds). The web group's session makes the owner
// check work for the inbox.
Route::get('photos/{value}', [TestimonialPhotoController::class, 'show'])
    ->whereNumber('value')
    ->name('photos.show');

// Public Wall of Love page (OpenSpec: public-wall-of-love). Auth-free
// GET — the page is the owner-shared marketing surface (Twitter,
// LinkedIn, email signatures), so the routing key is `slug` (human
// readable, owner-editable), not the immutable `public_id` used by
// the embed. No throttle — read-only, low traffic.
Route::get('wall/{slug}', [PublicWallController::class, 'show'])
    ->name('public.wall.show');

// Billing (OpenSpec: billing, PRD §24-§28). Checkout and portal both
// hand the browser to Stripe; the plan only changes via the webhook.
Route::middleware(['auth', 'verified'])->prefix('billing')->name('billing.')->group(function () {
    Route::get('/', [BillingController::class, 'show'])->name('show');
    Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
    Route::post('/portal', [BillingController::class, 'portal'])->name('portal');
});

// Cashier's routes, re-registered (AppServiceProvider calls
// Cashier::ignoreRoutes()) so the webhook resolves to our subclass.
// Same prefix and names Cashier would use; the webhook is CSRF-exempt
// in bootstrap/app.php and signature-verified by the controller.
Route::prefix(config('cashier.path'))->name('cashier.')->group(function () {
    Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
    Route::post('webhook', [StripeWebhookController::class, 'handleWebhook'])->name('webhook');
});

require __DIR__.'/settings.php';
