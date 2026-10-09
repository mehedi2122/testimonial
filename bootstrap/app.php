<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The public submission endpoint is reached from a third-party
        // website's DOM via fetch() — there is no same-origin guarantee and
        // therefore no CSRF cookie to validate. CSRF protection for the
        // endpoint comes from the rate limiter (60/IP/hour) and from server
        // owning consent validation, not from a Laravel CSRF cookie.
        //
        // Stripe webhooks are server-to-server; authenticity comes from the
        // Stripe-Signature header Cashier verifies, not a CSRF cookie.
        $middleware->validateCsrfTokens(except: [
            's/*/submissions',
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 419 "Page Expired": a form left open past the session lifetime
        // (or a tab still holding a pre-logout CSRF token). Instead of an
        // error page, send the user back to the form — which renders with
        // a fresh token — and tell them to resubmit.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($response->getStatusCode() !== 419 || $request->expectsJson()) {
                return $response;
            }

            $message = 'Your session expired. Please try again.';
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back()->with('status', $message);
        });
    })->create();
