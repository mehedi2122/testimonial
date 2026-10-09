<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Plan;
use App\Jobs\NotifyPaymentFailed;
use App\Jobs\SyncCheckoutSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Webhooks are the only thing that changes a user's plan (OpenSpec
 * change: billing, PRD §27-§28). Every request here is signed with a
 * test secret, exactly as Stripe signs real deliveries.
 */
class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cashier.webhook.secret' => self::SECRET]);
    }

    public function test_subscription_created_makes_the_user_pro(): void
    {
        $user = $this->customer('cus_1');

        $this->postSigned($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active'))
            ->assertOk();

        $this->assertSame(Plan::Pro, $user->fresh()?->plan());
    }

    public function test_subscription_deleted_returns_the_user_to_free(): void
    {
        $user = $this->customer('cus_1');
        $this->postSigned($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active'));

        $this->postSigned($this->subscriptionEvent('customer.subscription.deleted', 'cus_1', 'canceled'))
            ->assertOk();

        $this->assertSame(Plan::Free, $user->fresh()?->plan());
    }

    public function test_past_due_update_is_not_pro(): void
    {
        $user = $this->customer('cus_1');
        $this->postSigned($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active'));

        $this->postSigned($this->subscriptionEvent('customer.subscription.updated', 'cus_1', 'past_due'))
            ->assertOk();

        $this->assertSame(Plan::Free, $user->fresh()?->plan());
        $this->assertSame('past_due', $user->fresh()?->subscription('default')?->stripe_status);
    }

    public function test_cancellation_keeps_pro_until_the_period_ends(): void
    {
        $user = $this->customer('cus_1');
        $this->postSigned($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active'));

        $this->postSigned($this->subscriptionEvent('customer.subscription.updated', 'cus_1', 'active', [
            'cancel_at' => now()->addDays(10)->getTimestamp(),
        ]))->assertOk();

        $subscription = $user->fresh()?->subscription('default');
        $this->assertTrue((bool) $subscription?->onGracePeriod());
        $this->assertSame(Plan::Pro, $user->fresh()?->plan());
    }

    public function test_missing_secret_outside_local_fails_closed(): void
    {
        config(['cashier.webhook.secret' => null]);
        $this->app->detectEnvironment(fn (): string => 'production');
        $user = $this->customer('cus_1');

        $this->call(
            'POST',
            '/stripe/webhook',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active')),
        )->assertStatus(503);

        $this->assertSame(Plan::Free, $user->fresh()?->plan());
    }

    public function test_unsigned_request_is_rejected_and_changes_nothing(): void
    {
        $user = $this->customer('cus_1');

        $this->call(
            'POST',
            '/stripe/webhook',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: (string) json_encode($this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active')),
        )->assertForbidden();

        $this->assertSame(Plan::Free, $user->fresh()?->plan());
    }

    public function test_request_signed_with_the_wrong_secret_is_rejected(): void
    {
        $this->customer('cus_1');

        $this->postSigned(
            $this->subscriptionEvent('customer.subscription.created', 'cus_1', 'active'),
            'whsec_someone_else',
        )->assertForbidden();
    }

    public function test_checkout_completed_queues_the_reconciliation_job(): void
    {
        Queue::fake();
        $this->customer('cus_1');

        $this->postSigned([
            'id' => 'evt_checkout',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_1',
                'mode' => 'subscription',
                'customer' => 'cus_1',
                'subscription' => 'sub_1',
            ]],
        ])->assertOk();

        Queue::assertPushed(SyncCheckoutSubscription::class, fn (SyncCheckoutSubscription $job) => $job->customerId === 'cus_1' && $job->subscriptionId === 'sub_1');
    }

    public function test_checkout_completed_for_a_one_off_payment_queues_nothing(): void
    {
        Queue::fake();

        $this->postSigned([
            'id' => 'evt_payment',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_2', 'mode' => 'payment', 'customer' => 'cus_1', 'subscription' => null]],
        ])->assertOk();

        Queue::assertNothingPushed();
    }

    public function test_payment_failed_queues_the_notification_job(): void
    {
        Queue::fake();
        $this->customer('cus_1');

        $this->postSigned([
            'id' => 'evt_failed',
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['id' => 'in_1', 'customer' => 'cus_1']],
        ])->assertOk();

        Queue::assertPushed(NotifyPaymentFailed::class, fn (NotifyPaymentFailed $job) => $job->eventId === 'evt_failed' && $job->customerId === 'cus_1');
    }

    private function customer(string $stripeId): User
    {
        $user = User::factory()->create();
        $user->forceFill(['stripe_id' => $stripeId])->save();

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function subscriptionEvent(string $type, string $customer, string $status, array $overrides = []): array
    {
        return [
            'id' => 'evt_'.uniqid(),
            'type' => $type,
            'data' => ['object' => [
                'id' => 'sub_1',
                'customer' => $customer,
                'status' => $status,
                'metadata' => ['type' => 'default'],
                'trial_end' => null,
                'items' => ['data' => [[
                    'id' => 'si_1',
                    'quantity' => 1,
                    'price' => ['id' => 'price_pro', 'product' => 'prod_pro'],
                ]]],
                ...$overrides,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return TestResponse<Response>
     */
    private function postSigned(array $event, string $secret = self::SECRET): TestResponse
    {
        $payload = (string) json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $this->call(
            'POST',
            '/stripe/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            content: $payload,
        );
    }
}
