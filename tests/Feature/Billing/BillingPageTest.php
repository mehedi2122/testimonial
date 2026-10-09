<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\OpenBillingPortalAction;
use App\Actions\Billing\StartProCheckoutAction;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Stripe\Exception\ApiConnectionException;
use Tests\TestCase;

/**
 * HTTP coverage for /billing (OpenSpec change: billing, PRD §26-§27).
 * Stripe calls are replaced by partial mocks of the Actions' session
 * methods, so preflight rules still run for real.
 */
class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    private const CHECKOUT_URL = 'https://checkout.stripe.com/c/pay/cs_test_1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.pro_price' => 'price_pro']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('billing.show'))->assertRedirect(route('login'));
    }

    public function test_free_user_sees_both_plans(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(2)->create();

        $this->actingAs($user)
            ->get(route('billing.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/index')
                ->where('plan', 'free')
                ->where('plans.0.key', 'free')
                ->where('plans.0.price', '$0')
                ->where('plans.0.max_spaces', 3)
                ->where('plans.0.max_testimonials_per_space', 100)
                ->where('plans.1.key', 'pro')
                ->where('plans.1.price', '$9.99')
                ->where('plans.1.max_spaces', 25)
                ->where('plans.1.max_testimonials_per_space', 1000)
                ->where('usage.spaces', 2)
                ->where('usage.max_spaces', 3)
                ->where('subscription', null)
                ->where('configured', true)
            );
    }

    public function test_pro_user_sees_pro_as_current_plan(): void
    {
        $user = $this->proUser();

        $this->actingAs($user)
            ->get(route('billing.show'))
            ->assertInertia(fn ($page) => $page
                ->where('plan', 'pro')
                ->where('subscription.status', 'active')
                ->where('subscription.payment_failed', false)
                ->where('usage.max_spaces', 25)
                ->where('can_manage', true)
            );
    }

    public function test_success_redirect_does_not_activate_pro(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('billing.show', ['checkout' => 'success']))
            ->assertInertia(fn ($page) => $page
                ->where('plan', 'free')
                ->where('checkout', 'success')
            );
    }

    public function test_unknown_checkout_value_is_dropped(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('billing.show', ['checkout' => '<script>']))
            ->assertInertia(fn ($page) => $page->where('checkout', null));
    }

    public function test_past_due_subscription_is_flagged(): void
    {
        $user = $this->proUser('past_due');

        $this->actingAs($user)
            ->get(route('billing.show'))
            ->assertInertia(fn ($page) => $page
                ->where('plan', 'free')
                ->where('subscription.payment_failed', true)
            );
    }

    public function test_checkout_sends_free_user_to_stripe(): void
    {
        $user = User::factory()->create();
        $this->partialMock(StartProCheckoutAction::class, function ($mock) {
            $mock->shouldReceive('createSession')->once()->andReturn(self::CHECKOUT_URL);
        });

        $this->actingAs($user)
            ->post(route('billing.checkout'), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', self::CHECKOUT_URL);

        $this->assertNull($user->fresh()?->subscription('default'));
    }

    public function test_checkout_is_refused_for_pro_user(): void
    {
        $user = $this->proUser();
        $this->partialMock(StartProCheckoutAction::class, function ($mock) {
            $mock->shouldNotReceive('createSession');
        });

        $this->actingAs($user)
            ->from(route('billing.show'))
            ->post(route('billing.checkout'))
            ->assertRedirect(route('billing.show'))
            ->assertSessionHas('error', "You're already on the Pro plan.");
    }

    public function test_checkout_is_refused_while_a_failed_subscription_exists(): void
    {
        $user = $this->proUser('past_due');
        $this->partialMock(StartProCheckoutAction::class, function ($mock) {
            $mock->shouldNotReceive('createSession');
        });

        $this->actingAs($user)
            ->from(route('billing.show'))
            ->post(route('billing.checkout'))
            ->assertSessionHas('error');
    }

    public function test_checkout_is_refused_when_price_is_not_configured(): void
    {
        config(['services.stripe.pro_price' => null]);
        $this->partialMock(StartProCheckoutAction::class, function ($mock) {
            $mock->shouldNotReceive('createSession');
        });

        $this->actingAs(User::factory()->create())
            ->from(route('billing.show'))
            ->post(route('billing.checkout'))
            ->assertSessionHas('error');
    }

    public function test_stripe_outage_is_reported_as_a_friendly_error(): void
    {
        $this->partialMock(StartProCheckoutAction::class, function ($mock) {
            $mock->shouldReceive('createSession')->andThrow(new ApiConnectionException('down'));
        });

        $this->actingAs(User::factory()->create())
            ->from(route('billing.show'))
            ->post(route('billing.checkout'))
            ->assertRedirect(route('billing.show'))
            ->assertSessionHas('error', "We couldn't reach Stripe. Please try again in a moment.");
    }

    public function test_portal_sends_customer_to_stripe(): void
    {
        $user = $this->proUser();
        $this->partialMock(OpenBillingPortalAction::class, function ($mock) {
            $mock->shouldReceive('createSession')->once()->andReturn('https://billing.stripe.com/p/session/test');
        });

        $this->actingAs($user)
            ->post(route('billing.portal'), [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', 'https://billing.stripe.com/p/session/test');
    }

    public function test_portal_is_refused_without_a_stripe_customer(): void
    {
        $this->actingAs(User::factory()->create())
            ->from(route('billing.show'))
            ->post(route('billing.portal'))
            ->assertSessionHas('error');
    }

    public function test_free_space_limit_uses_prd_copy(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(3)->create();

        $this->actingAs($user)
            ->post(route('spaces.store'), [
                'name' => 'Fourth',
                'title' => 'Fourth',
                'ask' => 'How was it?',
                'theme' => 'minimal',
                'rating_enabled' => true,
            ])
            ->assertSessionHas('error', "You've reached the Free plan limit. Upgrade to Pro to create more Spaces.");
    }

    private function proUser(string $status = 'active'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['stripe_id' => 'cus_'.$user->id])->save();

        Subscription::query()->create([
            'user_id' => $user->id,
            'type' => 'default',
            'stripe_id' => 'sub_'.$user->id,
            'stripe_status' => $status,
            'stripe_price' => 'price_pro',
            'quantity' => 1,
        ]);

        return $user;
    }
}
