<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Actions\SubmitTestimonialAction;
use App\Enums\SpaceFieldMode;
use App\Models\Space;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Resolves a Space for the public submission form (OpenSpec change:
 * public-submission-form).
 *
 * Single Responsibility: given an opaque `public_id`, return the live
 * Space and the configured fields (skipping `mode = off`). The 404
 * preflight is the soft-delete check; an unknown or trashed id both
 * surface as ModelNotFoundException so the controller can `abort(404)`.
 *
 * Companion to {@see SubmitTestimonialAction} (server-side
 * persistence) — both actions live on the public submission surface but
 * own different boundaries.
 */
class ShowPublicSubmissionAction
{
    /**
     * @throws ModelNotFoundException
     */
    public function resolve(string $publicId): Space
    {
        return Space::query()
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->with([
                'fields' => fn ($query) => $query
                    ->where('mode', '!=', SpaceFieldMode::Off)
                    ->orderBy('sort_order'),
            ])
            ->firstOrFail();
    }
}
