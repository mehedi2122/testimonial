<?php

declare(strict_types=1);

namespace App\Actions\Inbox;

use App\Models\Testimonial;

/**
 * Flip the `is_wall_of_love` flag on a Testimonial (OpenSpec change:
 * testimonial-inbox).
 *
 * This flag DOES affect the §15 visibility rule:
 * `is_public = consent_given AND is_wall_of_love AND NOT is_hidden`.
 * Saving the row re-runs the boot hook on SQLite (and the STORED
 * generated column on MySQL) so `is_public` stays truthful — no
 * extra refresh needed.
 *
 * SRP: one method, one mutation. Returns the new state.
 */
class ToggleWallOfLoveAction
{
    public function toggle(Testimonial $testimonial): bool
    {
        $testimonial->is_wall_of_love = ! $testimonial->is_wall_of_love;
        $testimonial->save();

        return $testimonial->is_wall_of_love;
    }
}
