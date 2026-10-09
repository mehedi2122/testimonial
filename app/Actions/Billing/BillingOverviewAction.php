<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\Plan;
use App\Models\Space;
use App\Models\User;

/**
 * Read model for the Billing page (OpenSpec change: billing, PRD §26).
 *
 * Plan comes from {@see User::plan()} — i.e. from the `subscriptions`
 * table Cashier keeps in sync with Stripe webhooks. Nothing here looks
 * at the `?checkout=success` redirect except to choose copy: the
 * redirect never activates Pro (PRD §27).
 */
class BillingOverviewAction
{
    /** Stripe statuses where the subscription exists but isn't paying. */
    private const PAYMENT_FAILED_STATUSES = ['past_due', 'unpaid', 'incomplete'];

    /**
     * @return array{
     *     plan: string,
     *     plans: list<array{key: string, name: string, price: string, max_spaces: int, max_testimonials_per_space: int}>,
     *     usage: array{spaces: int, max_spaces: int},
     *     subscription: array{status: string, on_grace_period: bool, ends_at: string|null, payment_failed: bool}|null,
     *     checkout: string|null,
     *     can_manage: bool,
     *     configured: bool,
     * }
     */
    public function forUser(User $user, mixed $checkout = null): array
    {
        $plan = $user->plan();
        $subscription = $user->subscription('default');

        return [
            'plan' => $plan->value,
            'plans' => [
                $this->describe(Plan::Free, '$0'),
                $this->describe(Plan::Pro, '$9.99'),
            ],
            'usage' => [
                'spaces' => Space::query()->where('user_id', $user->id)->count(),
                'max_spaces' => $plan->maxSpaces(),
            ],
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->stripe_status,
                'on_grace_period' => $subscription->onGracePeriod(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'payment_failed' => in_array($subscription->stripe_status, self::PAYMENT_FAILED_STATUSES, true),
            ],
            'checkout' => in_array($checkout, ['success', 'canceled'], true) ? $checkout : null,
            'can_manage' => $user->hasStripeId(),
            'configured' => filled(config('services.stripe.pro_price')),
        ];
    }

    /**
     * @return array{key: string, name: string, price: string, max_spaces: int, max_testimonials_per_space: int}
     */
    private function describe(Plan $plan, string $price): array
    {
        return [
            'key' => $plan->value,
            'name' => ucfirst($plan->value),
            'price' => $price,
            'max_spaces' => $plan->maxSpaces(),
            'max_testimonials_per_space' => $plan->maxTestimonialsPerSpace(),
        ];
    }
}
