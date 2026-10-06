<?php

declare(strict_types=1);

namespace App\Actions\Inbox;

use App\Models\Testimonial;

/**
 * Flip the `is_favorite` flag on a Testimonial (OpenSpec change:
 * testimonial-inbox).
 *
 * SRP: one method, one mutation. Returns the new state so the
 * controller can craft a flash message that reflects what the row
 * actually became (favorite vs unfavorite).
 *
 * This flag does not affect `is_public` — favorites are an
 * owner-side curation tool that surfaces at the top of the inbox
 * (and, in the future, on the public wall-of-love).
 */
class ToggleFavoriteAction
{
    public function toggle(Testimonial $testimonial): bool
    {
        $testimonial->is_favorite = ! $testimonial->is_favorite;
        $testimonial->save();

        return $testimonial->is_favorite;
    }
}
