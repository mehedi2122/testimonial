<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Enums\SpaceFieldType;
use App\Models\TestimonialValue;
use App\Models\User;

/**
 * Who may see a stored profile photo (PRD §31).
 *
 *   - the Space owner, always (inbox moderation);
 *   - anyone else only when the testimonial is publicly visible
 *     (consent + Wall of Love + not hidden + not deleted) AND the
 *     photo field is shown on embeds.
 *
 * Returns the storage path to serve, or null for "404" — callers never
 * learn whether a photo exists when they may not see it.
 */
class ResolvePhotoAccessAction
{
    public function resolve(TestimonialValue $value, ?User $viewer): ?string
    {
        $field = $value->spaceField;
        $testimonial = $value->testimonial;

        if ($field === null || $testimonial === null || $field->type !== SpaceFieldType::Image) {
            return null;
        }

        if (! StoreTestimonialPhotoAction::isStoredPath($value->value)) {
            return null;
        }

        $space = $testimonial->space;
        $isOwner = $viewer !== null && $space !== null && $space->user_id === $viewer->id;

        if (! $isOwner) {
            $isPublic = $testimonial->consent_given
                && $testimonial->is_wall_of_love
                && ! $testimonial->is_hidden
                && $testimonial->deleted_at === null
                && $field->deleted_at === null
                && $field->show_in_embed
                && $space !== null
                && $space->deleted_at === null;

            if (! $isPublic) {
                return null;
            }
        }

        return $value->value;
    }
}
