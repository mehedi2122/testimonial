<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

/**
 * Owner-gates every authenticated moderation action on a Testimonial
 * (OpenSpec change: testimonial-inbox).
 *
 * The ownership check follows the Space it belongs to: any user who
 * owns the Space owns every Testimonial inside it. Eager-loading the
 * Space relation on the controller side is recommended when calling
 * these checks in loops.
 *
 * The three flag endpoints (favorite / wall-of-love / hidden) all funnel
 * through `moderate()` to keep the authorization surface narrow and
 * symmetric — adding a fourth flag is one new controller method but no
 * new policy method.
 */
class TestimonialPolicy
{
    /**
     * Owner can edit the testimonial's name, body, and rating.
     */
    public function update(User $user, Testimonial $testimonial): bool
    {
        return $this->ownsViaSpace($user, $testimonial);
    }

    /**
     * Owner can soft-delete the testimonial (matches the Spaces pattern
     * — the row stays archived and the URL/IDs stay reserved).
     */
    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->ownsViaSpace($user, $testimonial);
    }

    /**
     * Shared gate for the three flag toggles
     * (favorite / wall-of-love / hidden). The controller calls
     * `Gate::authorize('moderate', $testimonial)` instead of repeating
     * the same check three times.
     */
    public function moderate(User $user, Testimonial $testimonial): bool
    {
        return $this->ownsViaSpace($user, $testimonial);
    }

    /**
     * Single source of truth for ownership.
     *
     * `space_id` is set on the row directly so we don't *have* to
     * touch the relation — but `space` is the relationship the rest of
     * the codebase uses, so we go through it for consistency. The
     * relation is always loaded by the controller via route model
     * binding, so `->user_id` is safe here.
     */
    private function ownsViaSpace(User $user, Testimonial $testimonial): bool
    {
        return $testimonial->space->user_id === $user->id;
    }
}
