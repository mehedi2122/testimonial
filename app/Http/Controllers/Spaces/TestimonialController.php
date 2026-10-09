<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spaces;

use App\Actions\Inbox\DeleteTestimonialAction;
use App\Actions\Inbox\ToggleFavoriteAction;
use App\Actions\Inbox\ToggleHiddenAction;
use App\Actions\Inbox\ToggleWallOfLoveAction;
use App\Actions\Inbox\UpdateTestimonialAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTestimonialRequest;
use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Owner-side moderation endpoints for the inbox (OpenSpec change:
 * testimonial-inbox).
 *
 * Each method follows the same shape:
 *   1. PK-scope the Testimonial against the slug-bound Space so a
 *      cross-tenant id guess is a 404, not a 403.
 *   2. `Gate::authorize('moderate', $testimonial)` for the three flag
 *      endpoints, or the matching method for update / delete.
 *   3. Hand off to the action class.
 *   4. `back()` with a state-dependent flash message.
 *
 * The controller stays thin: business rules (sanitization, state
 * recomputation, soft-delete semantics) live in the actions, and
 * authorization lives in the policies.
 */
class TestimonialController extends Controller
{
    public function __construct(
        private readonly ToggleFavoriteAction $toggleFavorite,
        private readonly ToggleWallOfLoveAction $toggleWallOfLove,
        private readonly ToggleHiddenAction $toggleHidden,
        private readonly UpdateTestimonialAction $updateTestimonial,
        private readonly DeleteTestimonialAction $deleteTestimonial,
    ) {}

    public function favorite(Space $space, Testimonial $testimonial): RedirectResponse
    {
        $this->assertScoped($space, $testimonial);
        Gate::authorize('moderate', $testimonial);

        $isFavorite = $this->toggleFavorite->toggle($testimonial);

        return back()->with(
            'success',
            $isFavorite
                ? 'Marked as favorite.'
                : 'Removed from favorites.',
        );
    }

    public function wallOfLove(Space $space, Testimonial $testimonial): RedirectResponse
    {
        $this->assertScoped($space, $testimonial);
        Gate::authorize('moderate', $testimonial);

        if (! $this->toggleWallOfLove->canPublish($testimonial)) {
            return back()->with(
                'error',
                "This submitter didn't give permission to share their testimonial publicly, so it can't go on your wall of love.",
            );
        }

        $isOnWall = $this->toggleWallOfLove->toggle($testimonial);

        return back()->with(
            'success',
            $isOnWall
                ? 'Published to your wall of love.'
                : 'Removed from your wall of love.',
        );
    }

    public function hidden(Space $space, Testimonial $testimonial): RedirectResponse
    {
        $this->assertScoped($space, $testimonial);
        Gate::authorize('moderate', $testimonial);

        $isHidden = $this->toggleHidden->toggle($testimonial);

        return back()->with(
            'success',
            $isHidden
                ? 'Hidden from the wall of love.'
                : 'Visible on the wall of love again.',
        );
    }

    public function update(
        Space $space,
        Testimonial $testimonial,
        UpdateTestimonialRequest $request,
    ): RedirectResponse {
        $this->assertScoped($space, $testimonial);
        Gate::authorize('update', $testimonial);

        $this->updateTestimonial->update($testimonial, $request->validated());

        return back()->with('success', 'Testimonial updated.');
    }

    public function destroy(Space $space, Testimonial $testimonial): RedirectResponse
    {
        $this->assertScoped($space, $testimonial);
        Gate::authorize('delete', $testimonial);

        $this->deleteTestimonial->delete($testimonial);

        return back()->with('success', 'Testimonial deleted.');
    }

    /**
     * Cross-tenant guard: even if a user is allowed to view one of
     * their own Spaces, the Testimonial must belong to *that* Space
     * for the route segment to be a valid pair. Mismatches surface as
     * 404, not 403, so a foreign testimonial's existence is not
     * leaked.
     */
    private function assertScoped(Space $space, Testimonial $testimonial): void
    {
        abort_unless($testimonial->space_id === $space->id, 404);
    }
}
