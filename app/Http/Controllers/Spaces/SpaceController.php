<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spaces;

use App\Actions\CreateSpaceAction;
use App\Actions\Dashboards\DashboardAnalyticsAction;
use App\Actions\Embeds\UpdateEmbedConfigurationAction;
use App\Actions\UpdateSpaceSettingsAction;
use App\Enums\EmbedLayout;
use App\Enums\SpaceTheme;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSpaceRequest;
use App\Http\Requests\Spaces\UpdateEmbedConfigurationRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use App\Support\EmbedSnippet;
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
 *
 * All four space-scoped GETs (dashboard / inbox / embed / settings) run
 * `Gate::authorize('view', $space)`. Before the space-settings change
 * only `inbox` did, which left the other three open to any
 * authenticated user who guessed a slug.
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

    public function dashboard(
        Space $space,
        Request $request,
        DashboardAnalyticsAction $action,
    ): Response {
        Gate::authorize('view', $space);

        $range = (string) $request->query('range', DashboardAnalyticsAction::RANGE_30D);

        return Inertia::render('spaces/dashboard', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
            'analytics' => $action->for($space, $range),
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

    /**
     * Embed Builder (OpenSpec change: embed-builder). Replaces the
     * placeholder page with a 3-pane builder: configuration form
     * (left), live preview (right), copyable embed snippet (bottom).
     *
     * Authorization: `SpacePolicy::view` already covers the
     * form-prep step — the same `view` policy gates `dashboard`,
     * `inbox`, and `settings`. The PATCH route below uses
     * `updateEmbed` for symmetry with the rest of the controller.
     *
     * `embed` is the saved row OR a default-shaped stub when no
     * `embed_configurations` row exists yet — the page must render
     * before the owner has saved anything. `testimonials` is a small
     * slice of public testimonials used by the live preview (the
     * future `embed-widget` will use the same query).
     */
    public function embed(Space $space): Response
    {
        Gate::authorize('view', $space);

        $space->loadMissing('embedConfiguration', 'fields');

        $config = $space->embedConfiguration ?? $this->defaultEmbedConfiguration($space);

        $fields = $space->fields
            ->whereNull('deleted_at')
            ->sortBy('sort_order')
            ->values()
            ->map(fn (SpaceField $field): array => [
                'key' => $field->field_key,
                'label' => $field->label,
                'show_in_embed' => $field->show_in_embed,
            ])
            ->all();

        $testimonials = $space->testimonials()
            ->publiclyVisible()
            ->with('values.spaceField')
            ->limit(6)
            ->get()
            ->map(fn (Testimonial $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'testimonial' => $t->testimonial,
                'rating' => $t->rating,
                'is_favorite' => $t->is_favorite,
                'submitted_at' => $t->submitted_at->toIso8601String(),
                'values' => $t->values->map(fn (TestimonialValue $v): array => [
                    'field_key' => $v->spaceField?->field_key,
                    'label' => $v->spaceField?->label,
                    'value' => $v->value,
                ])->all(),
            ])->all();

        return Inertia::render('spaces/embed', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
                'public_id' => $space->public_id,
                'rating_enabled' => $space->rating_enabled,
            ],
            'embed' => [
                'layout' => $config->layout->value,
                'dark_mode' => $config->dark_mode,
                'animation_enabled' => $config->animation_enabled,
                'show_rating' => $config->show_rating,
                'background_color' => $config->background_color,
                'item_limit' => $config->item_limit,
            ],
            'fields' => $fields,
            'layouts' => array_map(
                fn (EmbedLayout $layout): array => [
                    'value' => $layout->value,
                    'label' => $layout->name,
                ],
                EmbedLayout::cases(),
            ),
            'testimonials' => $testimonials,
            'snippet' => EmbedSnippet::for($space, $config),
        ]);
    }

    /**
     * Persist Embed Builder form (OpenSpec change: embed-builder).
     * `Gate::authorize('updateEmbed', $space)` is the single gate; the
     * action does the field-visibility sync. Redirects back to the
     * page so the success flash + new snippet are visible immediately.
     */
    public function updateEmbed(
        Space $space,
        UpdateEmbedConfigurationRequest $request,
        UpdateEmbedConfigurationAction $action,
    ): RedirectResponse {
        Gate::authorize('updateEmbed', $space);

        $action->run($space, $request->validatedPayload());

        return redirect()
            ->route('spaces.embed', ['space' => $space->slug])
            ->with('success', 'Embed settings saved.');
    }

    /**
     * Shape a transient EmbedConfiguration with the schema defaults so
     * the page renders before the owner has saved anything. Never
     * persisted — `EmbedSnippet::for` only reads the public surface of
     * the model, so a non-persisted instance with the fields filled in
     * is enough.
     */
    private function defaultEmbedConfiguration(Space $space): EmbedConfiguration
    {
        $stub = new EmbedConfiguration;
        $stub->space_id = $space->id;
        $stub->layout = EmbedLayout::Masonry;
        $stub->dark_mode = false;
        $stub->animation_enabled = true;
        $stub->show_rating = true;
        $stub->background_color = null;
        $stub->item_limit = 12;

        return $stub;
    }

    /**
     * Settings editor (OpenSpec change: space-settings). Renders the
     * full mutable-field set so the React form can pre-fill every
     * input. `themes` mirrors the create page so the picker stays a thin
     * shim over the enum. `public_id` is shipped read-only so the
     * owner can copy the embed snippet without leaving the page.
     */
    public function settings(Space $space): Response
    {
        Gate::authorize('view', $space);

        return Inertia::render('spaces/settings', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
                'title' => $space->title,
                'subtitle' => $space->subtitle,
                'ask' => $space->ask,
                'theme' => $space->theme->value,
                'rating_enabled' => $space->rating_enabled,
                'public_id' => $space->public_id,
                'created_at' => $space->created_at?->toIso8601String(),
            ],
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
     * Persist owner edits to the Space's mutable fields
     * (OpenSpec change: space-settings). Validation runs through
     * {@see UpdateSpaceRequest}, ownership is enforced by
     * `SpacePolicy::update`, and the mutation is dispatched to
     * {@see UpdateSpaceSettingsAction}. Slug is regenerated server-side
     * when `name` changes, so we redirect to the *new* slug to keep the
     * round-trip clean.
     */
    public function updateSettings(
        Space $space,
        UpdateSpaceRequest $request,
        UpdateSpaceSettingsAction $action,
    ): RedirectResponse {
        Gate::authorize('update', $space);

        $space = $action->update($space, $request->validated());

        return redirect()
            ->route('spaces.settings', ['space' => $space->slug])
            ->with('success', 'Settings saved.');
    }
}
