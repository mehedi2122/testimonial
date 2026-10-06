<?php

declare(strict_types=1);

namespace App\Actions\Inbox;

use App\Models\Testimonial;
use App\Support\SanitizesFreeText;

/**
 * Edit an existing Testimonial's name, body, and rating (OpenSpec
 * change: testimonial-inbox).
 *
 * Email is intentionally NOT editable — that is the §15 data-model
 * guarantee (`testimonial_values` never holds an email, and the
 * submission email is the canonical contact for the row). Locking it
 * here keeps the moderation surface narrow.
 *
 * Free-text inputs (name, testimonial) go through the same
 * `SanitizesFreeText` rule the public endpoint applies, so the
 * storage shape stays identical regardless of who wrote it.
 *
 * `is_public` is recomputed by the model `saving` listener after the
 * mutate (rating does not affect visibility, but the listener fires on
 * every save and the recompute is cheap — no need to skip it).
 *
 * SRP: one method, one mutation. Returns the persisted model.
 */
class UpdateTestimonialAction
{
    use SanitizesFreeText;

    /**
     * @param  array<string, mixed>  $validated  validated form data
     */
    public function update(Testimonial $testimonial, array $validated): Testimonial
    {
        $testimonial->fill([
            'name' => $this->sanitize((string) ($validated['name'] ?? '')),
            'testimonial' => $this->sanitize((string) ($validated['testimonial'] ?? '')),
            'rating' => isset($validated['rating']) ? (int) $validated['rating'] : null,
        ]);

        $testimonial->save();

        return $testimonial;
    }
}
