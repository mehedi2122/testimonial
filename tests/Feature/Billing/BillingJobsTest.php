<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\SyncCheckoutSubscriptionAction;
use App\Enums\Plan;
use App\Jobs\NotifyPaymentFailed;
use App\Jobs\SyncCheckoutSubscription;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Subscription;
use Tests\TestCase;

/**
 * The queued halves of the webhook handlers (OpenSpec change: billing).
 * Both must be safe to run more than once — Stripe redelivers.
 */
class BillingJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_sync_creates_the_missing_subscription(): void
    {
        $user = $this->customer('cus_1');
        $this->partialMock(SyncCheckoutSubscriptionAction::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('fetchSubscription')
                ->once()
                ->with('sub_1')
                ->andReturn([
                    'id' => 'sub_1',
                    'customer' => 'cus_1',
                    'status' => 'active',
                    'metadata' => ['type' => 'default'],
                    'trial_end' => null,
                    'items' => ['data' => [[
                        'id' => 'si_1',
                        'quantity' => 1,
                        'price' => ['id' => 'price_pro', 'product' => 'prod_pro'],
                    ]]],
                ]);
        });

        dispatch_sync(new SyncCheckoutSubscription('cus_1', 'sub_1'));

        $this->assertSame(Plan::Pro, $user->fresh()?->plan());
    }

    public function test_checkout_sync_skips_stripe_when_the_row_exists(): void
    {
        $user = $this->customer('cus_1');
        Subscription::query()->create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_1',
            'stripe_status' => 'active',
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);
        $this->partialMock(SyncCheckoutSubscriptionAction::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldNotReceive('fetchSubscription');
        });

        dispatch_sync(new SyncCheckoutSubscription('cus_1', 'sub_1'));

        $this->assertSame(1, $user->subscriptions()->count());
    }

    public function test_checkout_sync_ignores_unknown_customers(): void
    {
        $this->partialMock(SyncCheckoutSubscriptionAction::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()->shouldNotReceive('fetchSubscription');
        });

        dispatch_sync(new SyncCheckoutSubscription('cus_nobody', 'sub_1'));

        $this->assertSame(0, Subscription::query()->count());
    }

    public function test_payment_failed_notifies_the_owner_once_per_event(): void
    {
        Notification::fake();
        $user = $this->customer('cus_1');

        dispatch_sync(new NotifyPaymentFailed('evt_1', 'cus_1'));
        dispatch_sync(new NotifyPaymentFailed('evt_1', 'cus_1'));

        Notification::assertSentToTimes($user, PaymentFailedNotification::class, 1);
    }

    public function test_payment_failed_notifies_again_for_a_new_event(): void
    {
        Notification::fake();
        $user = $this->customer('cus_1');

        dispatch_sync(new NotifyPaymentFailed('evt_1', 'cus_1'));
        dispatch_sync(new NotifyPaymentFailed('evt_2', 'cus_1'));

        Notification::assertSentToTimes($user, PaymentFailedNotification::class, 2);
    }

    public function test_payment_failed_mail_links_to_billing(): void
    {
        $user = $this->customer('cus_1');

        $mail = (new PaymentFailedNotification)->toMail($user);

        $this->assertSame(route('billing.show'), $mail->actionUrl);
    }

    private function customer(string $stripeId): User
    {
        $user = User::factory()->create();
        $user->forceFill(['stripe_id' => $stripeId])->save();

        return $user;
    }
}
