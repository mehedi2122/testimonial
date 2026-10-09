<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Billing\NotifyPaymentFailedAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued half of `invoice.payment_failed` (PRD §28): mail goes out off
 * the webhook request so Stripe gets its 200 without waiting on SMTP.
 */
class NotifyPaymentFailed implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $eventId,
        public string $customerId,
    ) {}

    public function handle(NotifyPaymentFailedAction $action): void
    {
        $action->run($this->eventId, $this->customerId);
    }
}
