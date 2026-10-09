# space-settings Specification

## Purpose

Give the Space owner a real `/spaces/{slug}/settings` editor for the mutable fields set at creation (`name`, `title`, `subtitle`, `ask`, `theme`, `rating_enabled`). Today these are write-once — `create.tsx` posts them and there is no path to revise them without dropping the row and starting over. This change also closes the outstanding authorization gap: `dashboard`, `embed`, and `settings` GETs are currently reachable by any authenticated user who guesses a slug, even though `inbox` already runs `Gate::authorize('view', $space)`.

The change is bounded to the Space owner's own row. Cross-tenant access remains a 403 (via `SpacePolicy::view` / `SpacePolicy::update`, both already shipped in the testimonial-inbox change).

---

## Requirements

### Requirement: The system SHALL expose `GET /spaces/{space}/settings` and `PATCH /spaces/{space}/settings` behind the existing `auth + verified` group

The system SHALL enforce this requirement.

Both routes live inside the `spaces.*` route group, route-bound on the `slug` column (`Space::getRouteKeyName()`). The PATCH route is `spaces.settings.update`. The settings page is the single landing target for the success flash, so the PATCH redirects back to the same URL with the (possibly new) slug.

#### Scenario: route table

- **WHEN** the route table is inspected
- **THEN** `GET /spaces/{space}/settings` and `PATCH /spaces/{space}/settings` are both registered and the PATCH sits immediately after the GET in the file.

### Requirement: All four space-scoped GETs SHALL be gated by `SpacePolicy::view`

The system SHALL enforce this requirement.

`/spaces/{space}/dashboard`, `/spaces/{space}/inbox`, `/spaces/{space}/embed`, and `/spaces/{space}/settings` all call `Gate::authorize('view', $space)` at the top of their controller method. `inbox` already did (inbox change); the other three now match. Non-owner sessions receive a 403 and the Space's mutable fields are never serialized into the Inertia payload.

#### Scenario: non-owner attempts dashboard

- **WHEN** an authenticated user who does not own the Space GETs `/spaces/{slug}/dashboard`
- **THEN** the response is 403 and the Inertia page is not rendered.

#### Scenario: non-owner attempts embed

- **WHEN** an authenticated user who does not own the Space GETs `/spaces/{slug}/embed`
- **THEN** the response is 403.

#### Scenario: non-owner attempts settings

- **WHEN** an authenticated user who does not own the Space GETs `/spaces/{slug}/settings`
- **THEN** the response is 403.

### Requirement: `GET /spaces/{space}/settings` SHALL render the editor pre-filled with the current Space values

The system SHALL enforce this requirement.

The Inertia `space` prop carries every mutable field (`id`, `slug`, `name`, `title`, `subtitle`, `ask`, `theme`, `rating_enabled`, `public_id`, `created_at`). The page also receives `themes`, the list of `SpaceTheme` cases shaped as `{ value, label }` (same shape `create.tsx` consumes). `public_id` is displayed read-only so the owner can copy the embed snippet.

#### Scenario: page renders

- **WHEN** the owner GETs `/spaces/{slug}/settings`
- **THEN** the page component is `spaces/settings` and `space.name` equals the row's stored `name` and `themes` has three entries.

### Requirement: `PATCH /spaces/{space}/settings` SHALL validate via `UpdateSpaceRequest`

The system SHALL enforce this requirement.

Required keys mirror `CreateSpaceRequest`:

- `name` — string, 2..80 chars
- `title` — string, 2..120 chars
- `subtitle` — nullable string, ≤200 chars
- `ask` — string, 5..500 chars
- `theme` — must be a `SpaceTheme` enum value
- `rating_enabled` — boolean

Custom messages and the `theme` enum rule follow the create request exactly so the failure copy is consistent across create and update.

#### Scenario: invalid payload

- **WHEN** any required key is missing, too short, or `theme` is not a `SpaceTheme` value
- **THEN** the response is 422 with validation errors and no row is mutated.

### Requirement: The mutation SHALL be performed by `UpdateSpaceSettingsAction`

The system SHALL enforce this requirement.

`UpdateSpaceSettingsAction::update(Space $space, array $validated): Space` is the only writer. It assigns the mutable fields and, when `$space->name !== $validated['name']`, regenerates the slug via the shared `UniqueSpaceSlug::for(...)` helper. The action returns the reloaded `Space`. Controllers do not call `Space::save()` directly.

#### Scenario: rename regenerates slug

- **WHEN** the owner PATCHes with `name = "Shiplog Reviews v2"` on a Space whose current name is "Shiplog Reviews"
- **THEN** the row's `slug` becomes `shiplog-reviews-v2` (or `shiplog-reviews-v2-{4 random chars}` on collision) and the response redirects to the new URL.

#### Scenario: same name no-op for slug

- **WHEN** the owner PATCHes with the same `name` they originally used
- **THEN** the row's `slug` is unchanged.

#### Scenario: slug collision on rename

- **WHEN** the owner renames a Space to a name whose base slug is already taken by another (live **or** soft-deleted) Space
- **THEN** the new slug is `{base}-{4 random chars}` (same policy as `CreateSpaceAction`).

### Requirement: The action SHALL preserve `public_id`, `created_at`, and existing testimonial ratings

The system SHALL enforce this requirement.

`public_id` is set once in `Space::creating` and never touched by the update path. `created_at` is not on `$fillable` and the action does not call `touch()` (we are not introducing a "last settings edit" timestamp in this change). Toggling `rating_enabled` from `true` to `false` does **not** cascade-null existing `Testimonial::rating` values; the flag only controls whether new submissions collect a rating.

#### Scenario: public_id immutable

- **WHEN** the owner PATCHes any field
- **THEN** the row's `public_id` before and after are identical.

#### Scenario: rating_enabled off does not null existing ratings

- **WHEN** the owner PATCHes `rating_enabled = false` on a Space that has 2 live testimonials with `rating = 5`
- **THEN** both testimonials still carry `rating = 5` after the save.

### Requirement: The PATCH endpoint SHALL be authorized by `SpacePolicy::update`

The system SHALL enforce this requirement.

The controller calls `Gate::authorize('update', $space)` after the FormRequest resolves the Space by slug. `SpacePolicy::update` (already shipped in the inbox change) is the single source of truth — `$user->id === $space->user_id`. Soft-deleted Spaces return 404 because the route-model binding honors the `SoftDeletes` global scope.

#### Scenario: non-owner PATCH

- **WHEN** an authenticated user who does not own the Space PATCHes `/spaces/{slug}/settings`
- **THEN** the response is 403 and no row is mutated.

#### Scenario: soft-deleted Space

- **WHEN** the owner PATCHes `/spaces/{slug}/settings` on a Space whose `deleted_at IS NOT NULL`
- **THEN** the response is 404.

### Requirement: A successful save SHALL redirect to the settings page with a `flash.success` message

The system SHALL enforce this requirement.

The controller issues `redirect()->route('spaces.settings', ['space' => $space->fresh()->slug])->with('success', 'Settings saved.')`. The `slug` parameter is the _new_ slug after any rename, so a rename round-trips the owner to the new URL without a 404.

#### Scenario: post-save redirect

- **WHEN** the owner PATCHes and the save succeeds
- **THEN** the response is 302 to `/spaces/{new-slug}/settings` with a session `success` flash.

### Requirement: The shared slug-collision helper SHALL be reusable

The system SHALL enforce this requirement.

`App\Support\UniqueSpaceSlug::for(string $name, ?int $ignoreId = null): string` is the single source of truth for Space slug generation. `CreateSpaceAction` calls it with `$ignoreId = null`; `UpdateSpaceSettingsAction` passes the mutating Space's `id` so a rename to the same name does not synthesize a `-xxxx` suffix. The helper tries up to 5 candidates; on exhaustion it throws `RuntimeException` with the same wording `CreateSpaceAction` used to throw.

#### Scenario: rename to current name

- **WHEN** the owner PATCHes with `name = "Shiplog Reviews"` on a Space whose current name is "Shiplog Reviews" and `id = 42`
- **THEN** the slug stays at its current value (the existence check ignores `id = 42`).

---

### Requirement: Owners SHALL configure each form field as Off, Optional or Required

The Create and Settings pages SHALL show every Space field with an Off / Optional / Required control (Name and Email are always required). Submitted modes SHALL apply only to this Space's live fields; unknown keys are ignored. New Spaces seed Address as Required (private by default) and the other predefined fields as Off.

#### Scenario: owner makes Company required

- **WHEN** the owner saves Settings with `fields.company_name = required`
- **THEN** the public form requires Company name

#### Scenario: invalid mode

- **WHEN** a mode other than off/optional/required is sent
- **THEN** validation fails on `fields.{key}`

## Out of scope (explicit)

- 301 redirects from a Space's old slug to a new one — separate concern (handled in the future if SEO matters).
- Field-mode toggling (the `embed-builder` OpenSpec change).
- Plan-limit re-validation on settings save — the limit gates _creation_, not _edits_.
- Audit log of settings changes — not in PRD §3 today.
- A "Discard changes" button — outside the request.
- Soft-deleted Space undelete UI — separate concern.
