<?php

namespace App\Enums;

/**
 * Plan resolution: `$user->subscribed('default')` ?Plan::Pro : Plan::Free.
 *
 * Limits live here on purpose (no plans table, no users.plan column).
 * Decision log #8: a plans table would hold two rows nobody can edit,
 * and limits are entangled with enforcement logic and the Stripe price ID.
 */
enum Plan: string
{
    case Free = 'free';
    case Pro = 'pro';

    public function maxSpaces(): int
    {
        return match ($this) {
            self::Free => 3,
            self::Pro => 25,
        };
    }

    public function maxTestimonialsPerSpace(): int
    {
        return match ($this) {
            self::Free => 100,
            self::Pro => 1000,
        };
    }
}
