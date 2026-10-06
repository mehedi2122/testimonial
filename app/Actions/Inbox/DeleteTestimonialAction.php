<?php

declare(strict_types=1);

namespace App\Actions\Inbox;

use App\Models\Testimonial;

/**
 * Soft-delete a Testimonial (OpenSpec change: testimonial-inbox).
 *
 * Mirrors the Space deletion pattern — `SoftDeletes::delete()` uses
 * `saveQuietly()` so the `saving` boot hook does not refresh
 * `is_public`, but `scopePubliclyVisible()` re-evaluates the rule at
 * read time and excludes soft-deleted rows. The wall-of-love is
 * unaffected by this action in any other way.
 *
 * SRP: one method, one mutation.
 */
class DeleteTestimonialAction
{
    public function delete(Testimonial $testimonial): void
    {
        $testimonial->delete();
    }
}
