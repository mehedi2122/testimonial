<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spaces;

use App\Actions\CreateSpaceAction;
use App\Enums\SpaceTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpaceRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Authenticated controller for the /spaces/* tree.
 *
 * Route model binding uses the Space's `slug` column rather than the
 * primary key, per PRD §3.2 ("slug → /s/{slug}, user-editable, for
 * respondents"). Public submissions still key on `public_id`
 * (immutable, embed-targeted).
 *
 * Authorization on per-row mutations goes through `SpacePolicy` (auto-
 * discovered). The policy class covers view / update / delete — adding
 * a new mutation surface is a one-line `Gate::authorize(...)` call,
 * not a new inline `abort_unless`.
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
     * the row without URL collisions. Authorization goes through
     * SpacePolicy (auto-discovered).
     */
    public function destroy(Space $space, Request $request): RedirectResponse
    {
        Gate::authorize('delete', $space);

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

    /**
     * Owner-facing moderation queue (OpenSpec change: testimonial-inbox).
     *
     * Returns every live testimonial for the Space, ordered
     * favorites-first → newest-first (matches
     * `Testimonial::scopePubliclyVisible`'s ordering — the inbox is the
     * "behind-the-scenes" twin of the public wall). Custom-field answers
     * are eager-loaded with their parent SpaceField so the React side
     * can render `Label: Value` rows without a second round-trip.
     *
     * Plan-limit indicator at the top of the page comes from the
     * owner's `plan()` — Free caps at 100, Pro at 1000.
     */
    public function inbox(Space $space, Request $request): Response
    {
        Gate::authorize('view', $space);

        $testimonials = $space->testimonials()
            ->live()
            ->with(['values.spaceField'])
            ->orderByDesc('is_favorite')
            ->orderByDesc('submitted_at')
            ->get();

        $owner = $space->user;
        $plan = $owner->plan();
        $limit = $plan->maxTestimonialsPerSpace();

        return Inertia::render('spaces/inbox', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
            'testimonials' => $testimonials->map(fn (Testimonial $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'email' => $t->email,
                'testimonial' => $t->testimonial,
                'rating' => $t->rating,
                'is_favorite' => $t->is_favorite,
                'is_wall_of_love' => $t->is_wall_of_love,
                'is_hidden' => $t->is_hidden,
                'submitted_at' => $t->submitted_at->toIso8601String(),
                'values' => $t->values->map(fn (TestimonialValue $v): array => [
                    'field_key' => $v->spaceField?->field_key,
                    'label' => $v->spaceField?->label,
                    'value' => $v->value,
                ])->all(),
            ])->all(),
            'live_count' => $testimonials->count(),
            'plan_limit' => $limit,
            'plan' => $plan->value,
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
