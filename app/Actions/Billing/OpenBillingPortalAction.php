<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\User;

/**
 * Stripe Billing Portal for cancel / card updates (OpenSpec change:
 * billing). Changes made there come back through the
 * `customer.subscription.updated` / `.deleted` webhooks.
 */
class OpenBillingPortalAction
{
    public function preflight(User $user): ?string
    {
        if (! $user->hasStripeId()) {
            return 'There is no subscription to manage yet.';
        }

        return null;
    }

    public function createSession(User $user): string
    {
        return $user->billingPortalUrl(route('billing.show'));
    }
}
