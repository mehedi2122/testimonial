<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Cache;

/**
 * `invoice.payment_failed` → email the owner (OpenSpec change: billing).
 *
 * Stripe retries webhook delivery for up to three days, so the same
 * event id can arrive more than once (data-model §7.2). `Cache::add`
 * is atomic: only the first delivery claims the key and sends mail.
 * The plan itself is not touched here — the accompanying
 * `customer.subscription.updated` (status `past_due`) does that.
 */
class NotifyPaymentFailedAction
{
    public function run(string $eventId, string $customerId): void
    {
        if (! Cache::add('stripe-event:'.$eventId, true, now()->addDays(3))) {
            return;
        }

        $user = User::query()->where('stripe_id', $customerId)->first();

        if ($user === null) {
            return;
        }

        $user->notify(new PaymentFailedNotification);
    }
}
