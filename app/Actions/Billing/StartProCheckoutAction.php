<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Plan;
use App\Models\User;

/**
 * Free → Pro upgrade via Stripe Checkout (OpenSpec change: billing,
 * PRD §27).
 *
 * `preflight()` returns a user-facing error, or null when the session
 * may be created. `createSession()` talks to Stripe and returns the
 * hosted Checkout URL. Creating the session changes nothing locally
 * except the Stripe customer id: the plan flips only when the
 * `customer.subscription.created` webhook lands.
 */
class StartProCheckoutAction
{
    public function preflight(User $user): ?string
    {
        if ($user->plan() === Plan::Pro) {
            return "You're already on the Pro plan.";
        }

        // A past_due / incomplete subscription counts as Free, but it still
        // exists in Stripe: a second Checkout would double-bill. The fix is
        // a new card in the portal, not another subscription.
        $existing = $user->subscription('default');
        if ($existing !== null && ! $existing->ended()) {
            return 'Your Pro subscription has a payment problem. Update your payment method instead.';
        }

        if (blank(config('services.stripe.pro_price'))) {
            return "Billing isn't configured yet. Set STRIPE_PRICE_PRO to the Pro price id.";
        }

        return null;
    }

    public function createSession(User $user): string
    {
        $checkout = $user
            ->newSubscription('default', (string) config('services.stripe.pro_price'))
            ->checkout([
                'success_url' => route('billing.show', ['checkout' => 'success']),
                'cancel_url' => route('billing.show', ['checkout' => 'canceled']),
            ]);

        return (string) $checkout->asStripeCheckoutSession()->url;
    }
}
