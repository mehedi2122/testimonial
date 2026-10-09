<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\BillingOverviewAction;
use App\Actions\Billing\OpenBillingPortalAction;
use App\Actions\Billing\StartProCheckoutAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Billing page + the two hand-offs to Stripe (OpenSpec change: billing).
 * Thin by design: preflight → Stripe URL → `Inertia::location`, which
 * makes the browser leave the SPA for Stripe's hosted page.
 */
class BillingController extends Controller
{
    public function show(Request $request, BillingOverviewAction $action): Response
    {
        return Inertia::render('billing/index', $action->forUser(
            $this->user($request),
            $request->query('checkout'),
        ));
    }

    public function checkout(Request $request, StartProCheckoutAction $action): SymfonyResponse|RedirectResponse
    {
        $user = $this->user($request);

        if ($error = $action->preflight($user)) {
            return back()->with('error', $error);
        }

        try {
            return Inertia::location($action->createSession($user));
        } catch (ApiErrorException $e) {
            report($e);

            return back()->with('error', "We couldn't reach Stripe. Please try again in a moment.");
        }
    }

    public function portal(Request $request, OpenBillingPortalAction $action): SymfonyResponse|RedirectResponse
    {
        $user = $this->user($request);

        if ($error = $action->preflight($user)) {
            return back()->with('error', $error);
        }

        try {
            return Inertia::location($action->createSession($user));
        } catch (ApiErrorException $e) {
            report($e);

            return back()->with('error', "We couldn't reach Stripe. Please try again in a moment.");
        }
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
