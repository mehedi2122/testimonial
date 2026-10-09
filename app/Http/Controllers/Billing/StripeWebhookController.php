<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Jobs\NotifyPaymentFailed;
use App\Jobs\SyncCheckoutSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe webhook endpoint (OpenSpec change: billing, PRD §28).
 *
 * Extends Cashier's controller, so signature verification (when
 * `cashier.webhook.secret` is set) and the `customer.subscription.*`
 * handlers — the ones that actually change the plan — are Cashier's.
 * The two events Cashier doesn't handle are pushed onto the queue.
 */
class StripeWebhookController extends WebhookController
{
    /**
     * Fail closed: Cashier only verifies signatures when a secret is
     * set, and an unverified endpoint lets anyone forge
     * `customer.subscription.created` and get Pro for free. Outside
     * local/testing, a missing secret is a misconfiguration — refuse.
     */
    public function handleWebhook(Request $request): Response
    {
        if (blank(config('cashier.webhook.secret')) && ! app()->environment('local', 'testing')) {
            Log::critical('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured.');

            abort(503, 'Webhook signing secret is not configured.');
        }

        return parent::handleWebhook($request);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        $session = $payload['data']['object'] ?? [];

        if (
            ($session['mode'] ?? null) === 'subscription'
            && is_string($session['customer'] ?? null)
            && is_string($session['subscription'] ?? null)
        ) {
            SyncCheckoutSubscription::dispatch($session['customer'], $session['subscription']);
        }

        return $this->successMethod();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        $invoice = $payload['data']['object'] ?? [];

        if (is_string($payload['id'] ?? null) && is_string($invoice['customer'] ?? null)) {
            NotifyPaymentFailed::dispatch($payload['id'], $invoice['customer']);
        }

        return $this->successMethod();
    }

    /**
     * Apply a Stripe subscription object through Cashier's created-handler.
     * Used by the checkout reconciliation so `subscriptions` has a single
     * writer.
     *
     * @param  array<string, mixed>  $subscription
     */
    public function applySubscriptionCreated(array $subscription): void
    {
        $this->handleCustomerSubscriptionCreated(['data' => ['object' => $subscription]]);
    }
}
