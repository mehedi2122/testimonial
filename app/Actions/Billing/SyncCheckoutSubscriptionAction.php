<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Models\User;
use Laravel\Cashier\Cashier;

/**
 * `checkout.session.completed` reconciliation (OpenSpec change: billing,
 * data-model §7.2).
 *
 * Stripe doesn't guarantee event order, so the checkout event can land
 * before (or instead of) `customer.subscription.created`. When the local
 * row is missing, re-read the subscription from Stripe and apply it
 * through Cashier's own created-handler, so there is one code path that
 * writes `subscriptions`. Replay-safe: a second run finds the row and
 * does nothing.
 */
class SyncCheckoutSubscriptionAction
{
    public function run(string $customerId, string $subscriptionId): void
    {
        $user = User::query()->where('stripe_id', $customerId)->first();

        if ($user === null) {
            return;
        }

        if ($user->subscriptions()->where('stripe_id', $subscriptionId)->exists()) {
            return;
        }

        app(StripeWebhookController::class)->applySubscriptionCreated(
            $this->fetchSubscription($subscriptionId),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchSubscription(string $subscriptionId): array
    {
        return Cashier::stripe()->subscriptions->retrieve($subscriptionId)->toArray();
    }
}
