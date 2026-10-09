<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

/**
 * Owner-gates every authenticated action on a Space (OpenSpec change:
 * testimonial-inbox).
 *
 * Laravel 11/12 auto-discovers this from app/Policies because the
 * subject (`App\Models\Space`) lives under app/Models, so no manual
 * registration is needed.
 *
 * Single Rule: `$user->id === $space->user_id`. There is no
 * collaborator / member concept in the PRD; an "I don't own this"
 * answer is always 403.
 */
class SpacePolicy
{
    /**
     * Owner can view the Space in the authenticated app (dashboard,
     * inbox, embed-builder, settings, etc.).
     */
    public function view(User $user, Space $space): bool
    {
        return $user->id === $space->user_id;
    }

    /**
     * Owner can update the Space's mutable fields (theme, ask,
     * rating_enabled, etc.). Reserved for the future `space-settings`
     * OpenSpec change.
     */
    public function update(User $user, Space $space): bool
    {
        return $user->id === $space->user_id;
    }

    /**
     * Owner can soft-delete the Space (used by the existing
     * SpaceController::destroy now that authorization is centralized
     * here).
     */
    public function delete(User $user, Space $space): bool
    {
        return $user->id === $space->user_id;
    }

    /**
     * Owner can update the Space's embed configuration (layout, theme,
     * visibility toggles). Used by `SpaceController::embed` (GET — for
     * preparing the form) and `SpaceController::updateEmbed` (PATCH).
     * OpenSpec change: embed-builder.
     */
    public function updateEmbed(User $user, Space $space): bool
    {
        return $user->id === $space->user_id;
    }
}
