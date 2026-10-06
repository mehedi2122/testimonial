<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spaces;

use App\Actions\CreateSpaceAction;
use App\Enums\SpaceTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpaceRequest;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated controller for the /spaces/* tree.
 *
 * Group 13 (Agentic Application Shell Refactoring): placeholder
 * renders. Subsequent OpenSpec changes (space-crud, space-settings,
 * testimonial-inbox, embed-builder) extend this class with the matching
 * actions.
 *
 * Route model binding uses the Space's `slug` column rather than the
 * primary key, per PRD §3.2 ("slug → /s/{slug}, user-editable, for
 * respondents"). Public submissions still key on `public_id`
 * (immutable, embed-targeted).
 *
 * Authorization on per-row mutations (destroy, future update) is checked
 * explicitly via `abort_unless($space->user_id === $request->user()->id, 403)`.
 * A Space Policy is the next move once we have ≥2 mutation surfaces —
 * one inline check today, two tomorrow.
 */
class SpaceController extends Controller
{
    /**
     * List the authenticated user's Spaces. The Inertia page reads the
     * spaces from `auth.user.spaces` (already shared by
     * HandleInertiaRequests); the explicit query here is for the future
     * "Spaces you manage" count summary widget.
     */
    public function index(Request $request): Response
    {
        $spaces = $request->user()
            ->spaces()
            ->latest()
            ->get(['id', 'name', 'slug', 'public_id', 'theme', 'rating_enabled', 'created_at']);

        return Inertia::render('spaces/index', [
            'spaces' => $spaces->map(fn (Space $space): array => [
                'id' => $space->id,
                'name' => $space->name,
                'slug' => $space->slug,
                'theme' => $space->theme->value,
                'rating_enabled' => $space->rating_enabled,
                'created_at' => $space->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Render the create form. The page receives all SpaceTheme cases so
     * the picker stays a thin UI shim over the enum — no hardcoded list.
     */
    public function create(): Response
    {
        return Inertia::render('spaces/create', [
            'themes' => array_map(
                fn (SpaceTheme $theme): array => [
                    'value' => $theme->value,
                    'label' => $theme->name,
                ],
                SpaceTheme::cases(),
            ),
        ]);
    }

    /**
     * Persist a new Space for the authenticated user. Plan-limit
     * preflight runs first; on a hit we redirect to /spaces with a
     * session flash so the index page can render the upgrade copy.
     */
    public function store(
        CreateSpaceRequest $request,
        CreateSpaceAction $action,
    ): RedirectResponse {
        $user = $request->user();

        if ($violation = $action->checkPlanLimit($user)) {
            return redirect()
                ->route('spaces.index')
                ->with(
                    'error',
                    "You've reached your {$violation['plan']->value} plan limit of {$violation['limit']} Spaces. Upgrade to Pro to create more."
                );
        }

        $space = $action->create($user, $request->validated());

        return redirect()
            ->route('spaces.dashboard', ['space' => $space->slug])
            ->with('success', "Space \"{$space->name}\" created.");
    }

    /**
     * Soft-delete a Space the authenticated user owns. Soft delete (not
     * force) so the slug stays reserved and a future undelete can restore
     * the row without URL collisions.
     */
    public function destroy(Space $space, Request $request): RedirectResponse
    {
        abort_unless($space->user_id === $request->user()->id, 403);

        $name = $space->name;
        $space->delete();

        return redirect()
            ->route('spaces.index')
            ->with('success', "Space \"{$name}\" deleted.");
    }

    public function dashboard(Space $space): Response
    {
        return Inertia::render('spaces/dashboard', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }

    public function inbox(Space $space): Response
    {
        return Inertia::render('spaces/inbox', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }

    public function embed(Space $space): Response
    {
        return Inertia::render('spaces/embed', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
                'public_id' => $space->public_id,
            ],
        ]);
    }

    public function settings(Space $space): Response
    {
        return Inertia::render('spaces/settings', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }
}
