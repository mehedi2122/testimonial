<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Billing\SyncCheckoutSubscriptionAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued half of `checkout.session.completed` (PRD §28). The webhook
 * request returns 200 immediately; the Stripe API read happens here.
 */
class SyncCheckoutSubscription implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(
        public string $customerId,
        public string $subscriptionId,
    ) {}

    public function handle(SyncCheckoutSubscriptionAction $action): void
    {
        $action->run($this->customerId, $this->subscriptionId);
    }
}
