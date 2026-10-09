# embed-builder Specification

## Purpose

Replace the placeholder at `/spaces/{slug}/embed` with the owner-facing Embed Builder promised in PRD §19–§22. Building on the data shipped in Group 12 (`embed_configurations` table, `EmbedConfiguration` model, `EmbedLayout` enum) and the route scaffolded in Group 14 (`SpaceController::embed`), this change gives the owner a 3-pane page: a configuration form on the left, a live preview on the right, and a copyable embed snippet at the bottom. Saving the form persists the Space's `embed_configurations` row and syncs per-field visibility on `space_fields`.

The actual external-site widget — what runs on the customer's homepage — is a separate OpenSpec change (`embed-widget`) that reads the same row. This change is bounded to the owner authoring their configuration.

Authorization: `SpacePolicy::updateEmbed` (new in this change) is the gate for both the GET (preparing the form) and the PATCH (saving it). It is identical in rule to `view` and `update` — owner-only — and reuses the same `$user->id === $space->user_id` check.

---

## ADDED Requirements

### Requirement: `GET /spaces/{space}/embed` SHALL render the Embed Builder instead of the placeholder

The system SHALL enforce this requirement.

The page renders the existing `SpacePageShell` chrome plus a 3-pane builder. The Inertia props are `space` (id, slug, name, public_id), `embed` (the current `embed_configurations` row, or code defaults — see below — when none exists yet), `fields` (each `space_fields` row as `{ key, label, show_in_embed }`), `testimonials` (up to 6 most-recent public testimonials, API-resource-whitelisted, used by the live preview), and `snippet` (the HTML+JS embed code string, see Requirement below).

When no `embed_configurations` row exists yet, `embed` is built from these defaults so the page renders before any save: `layout = masonry`, `dark_mode = false`, `animation_enabled = true`, `background_color = null`, `item_limit = 12`, `show_rating = true`.

#### Scenario: first visit (no saved row)

- **WHEN** the owner GETs `/spaces/{slug}/embed` and no `embed_configurations` row exists for the Space
- **THEN** the Inertia page is `spaces/embed`, the page renders without error, the form pre-fills with the defaults above, and the snippet reflects those defaults.

#### Scenario: subsequent visit (saved row)

- **WHEN** the owner GETs `/spaces/{slug}/embed` and an `embed_configurations` row exists
- **THEN** the form pre-fills with the row's values (e.g. `layout = carousel`, `dark_mode = true`, `background_color = "#0F172A"`, `item_limit = 24`).

### Requirement: `PATCH /spaces/{space}/embed` SHALL persist the configuration and sync per-field visibility

The system SHALL enforce this requirement.

The form POSTs to the PATCH route. `App\Http\Requests\Spaces\UpdateEmbedConfigurationRequest` validates the input. `App\Actions\Embeds\UpdateEmbedConfigurationAction::run(Space, array): EmbedConfiguration` writes the row via `updateOrCreate(['space_id' => $space->id], [...])` and, when `field_visibility` is present in the validated array, syncs `space_fields.show_in_embed` for the Space's live fields. Unknown `field_key`s in `field_visibility` are ignored. The action is the only writer; controllers and tests do not call `EmbedConfiguration::save()` directly.

After a successful save, the controller redirects back to `/spaces/{slug}/embed` with a `success` flash: "Embed settings saved."

#### Scenario: first save creates the row

- **WHEN** the owner submits the form for the first time with `{ layout: "carousel", dark_mode: true, item_limit: 24, ... }`
- **THEN** a new `embed_configurations` row is created for the Space, the response is 302 to `/spaces/{slug}/embed`, and the success flash is set.

#### Scenario: subsequent save updates the row

- **WHEN** the owner submits the form again with `{ layout: "masonry", item_limit: 8, ... }`
- **THEN** the existing row is updated (not duplicated), the response is 302, and the success flash is set.

#### Scenario: field_visibility syncs space_fields

- **WHEN** the owner toggles "Show Company on embed" off and saves
- **THEN** `space_fields.show_in_embed` for the `company` field of that Space becomes `false`, and the next GET reflects the toggle in the `fields` prop.

#### Scenario: unknown field_key is ignored

- **WHEN** the form payload includes `field_visibility: { "company": true, "__proto__": true }`
- **THEN** only the `company` field is touched. No `space_fields` row is created, no error is raised.

### Requirement: The action SHALL normalize `background_color` and clamp `item_limit`

The system SHALL enforce this requirement.

`background_color` is stored as an uppercase 7-char `#RRGGBB` string or `null`. Empty string, missing, or `null` input is normalized to `null`. Lowercase hex (`#abcdef`) is normalized to uppercase (`#ABCDEF`). Invalid hex (e.g. `"red"`, `"#FFF"`) is rejected by the FormRequest regex `^#[0-9A-Fa-f]{6}$` and never reaches the action.

`item_limit` is clamped to `1..EmbedConfiguration::MAX_ITEM_LIMIT` (50). The FormRequest already enforces the same range, so the clamp in the action is defence in depth — e.g. a future direct caller cannot exceed it.

#### Scenario: lowercase hex is normalized

- **WHEN** the form posts `background_color = "#abcdef"`
- **THEN** the saved value is `"#ABCDEF"`.

#### Scenario: empty color clears the field

- **WHEN** the form posts `background_color = ""`
- **THEN** the saved value is `null`.

#### Scenario: item_limit clamped to MAX

- **WHEN** the form posts `item_limit = 1000` (would never pass validation, but the action is robust)
- **THEN** the saved value is `50`.

### Requirement: The page SHALL generate a copyable embed snippet reflecting the current configuration

The system SHALL enforce this requirement.

`App\Support\EmbedSnippet::for(Space, EmbedConfiguration): string` is a pure function that returns the HTML+JS snippet the owner pastes on their external site. The shape is:

```html
<div
    data-testimonial-space="{public_id}"
    data-style="{layout}"
    data-theme="{light|dark}"
    data-bg="{background_color or empty}"
    data-limit="{item_limit}"
    data-show-rating="{0|1}"
></div>
<script src="{origin}/embed.js" defer></script>
```

`origin` is the app's public URL (the same value `Inertia::share` exposes as `app.url`, or `config('app.url')` server-side). The snippet is rendered server-side and shipped in the Inertia `snippet` prop, so the React component never has to construct it from scratch. The Copy button uses `navigator.clipboard.writeText`; on success, the button label briefly reads "Copied!" for 2 seconds.

#### Scenario: snippet reflects saved config

- **WHEN** the owner saves `layout = carousel, dark_mode = true, background_color = "#0F172A", item_limit = 24, show_rating = false`
- **THEN** the rendered snippet is:

```html
<div
    data-testimonial-space="..."
    data-style="carousel"
    data-theme="dark"
    data-bg="#0F172A"
    data-limit="24"
    data-show-rating="0"
></div>
<script src="https://.../embed.js" defer></script>
```

#### Scenario: empty background_color omits data-bg

- **WHEN** `background_color` is `null`
- **THEN** the `data-bg` attribute is omitted entirely (no `data-bg=""`).

### Requirement: The page SHALL render a live preview that re-renders on form change

The system SHALL enforce this requirement.

The right pane shows up to 6 testimonial cards using the same Card primitive as `inbox.tsx` (no new component). The preview respects `dark_mode` (CSS class on a wrapper), `background_color` (inline style on the wrapper, or the page's neutral when null), `item_limit` (clamp the displayed list to that many), `show_rating` (hide the rating row when false), and `layout` (CSS class: `grid-cols-1` for masonry, `flex snap-x snap-mandatory overflow-x-auto` for carousel). Animation toggle adds a CSS class that triggers a subtle fade-in; whether or not it actually animates is a future `embed-widget` concern — the toggle is a no-op marker for the preview.

The preview re-renders on every form change (React state). It does not hit any new endpoint.

#### Scenario: dark mode flips theme

- **WHEN** the owner toggles `dark_mode` on
- **THEN** the preview wrapper gains the `dark` Tailwind class and the card backgrounds invert.

#### Scenario: layout switches between masonry and carousel

- **WHEN** the owner switches `layout` from `masonry` to `carousel`
- **THEN** the preview container's class changes from `grid grid-cols-1 gap-4` to `flex snap-x snap-mandatory gap-4 overflow-x-auto`.

#### Scenario: show_rating off hides rating row

- **WHEN** `show_rating = false` and the Space has `rating_enabled = true`
- **THEN** the rating row is not rendered in any preview card. The Space-level `rating_enabled = false` case hides ratings regardless (existing behavior; the field is disabled with a helper text in the form).

### Requirement: The form SHALL render one visibility toggle per `space_fields` row

The system SHALL enforce this requirement.

PRD §20 lists "show/hide profile photo" alongside rating. Per decision log #10 and the data-model §3.5 §3.6 split, the schema is: rating lives on `embed_configurations.show_rating`; every other field (including profile photo, company, social, custom) lives on `space_fields.show_in_embed`. The form reconciles these by showing the rating toggle in a "Visibility" group, and one row-toggle per `space_fields` row below it. The owner-facing label for each row uses the field's `label` (e.g. "Show Company on embed").

The `show_rating` form input is disabled with a helper note ("Ratings are disabled for this Space.") when `space.rating_enabled = false`.

#### Scenario: profile photo toggle

- **WHEN** the Space has a `space_fields` row `{ key: "photo_url", label: "Photo URL" }`
- **THEN** the form renders a Switch labeled "Show Photo URL on embed" bound to that row's `show_in_embed`.

#### Scenario: rating toggle disabled when ratings off

- **WHEN** `space.rating_enabled = false`
- **THEN** the `show_rating` Switch is rendered but disabled, with a helper line explaining why.

### Requirement: The action SHALL live under `app/Actions/Embeds/`

The system SHALL enforce this requirement.

`App\Actions\Embeds\UpdateEmbedConfigurationAction` is the only writer. It mirrors the action pattern from `app/Actions/Inbox/`, `app/Actions/Dashboards/`, and `app/Actions/Public/`: one public `run` method, controllers and FormRequests are thin.

#### Scenario: action location

- **WHEN** the file tree is inspected
- **THEN** `app/Actions/Embeds/UpdateEmbedConfigurationAction.php` exists and has exactly one public method.

### Requirement: Authorization SHALL be a single `SpacePolicy::updateEmbed` method

The system SHALL enforce this requirement.

The new method has the same body as `view`/`update`/`delete`: `return $user->id === $space->user_id;`. Both `SpaceController::embed` (GET) and `SpaceController::updateEmbed` (PATCH) call `Gate::authorize('updateEmbed', $space)`. A non-owner receives 403 on both routes.

#### Scenario: owner can save

- **WHEN** the owner PATCHes `/spaces/{slug}/embed`
- **THEN** the response is 302 and the row is updated.

#### Scenario: non-owner gets 403

- **WHEN** a different authenticated user PATCHes `/spaces/{slug}/embed`
- **THEN** the response is 403 and the row is unchanged.

---

## Out of scope (explicit)

- The actual external-site widget (`<script src=".../embed.js">` and its loader) — `embed-widget` (Group 20). This change only authors and stores the configuration; the widget that consumes it ships separately.
- The public Wall-of-Love page at `/wall/{slug}` — `public-wall-of-love` (Group 21).
- Multiple embeds per Space — explicitly declined in data-model §7.5.
- Custom CSS / per-field theming beyond `background_color` and `dark_mode`. PRD §20 says "Keep the configuration minimal."
- `display_options` JSON column — declined in decision log #10. Per-field `show_in_embed` + `embed_configurations.show_rating` cover the requirement.
- Embed impression / click analytics. No event collection in MVP.
- The actual `embed.js` script — this is `embed-widget`'s deliverable. The script tag emitted by the snippet is a placeholder for that future change.
- A `display_options` JSON column. The form's `field_visibility` is a typed map of `field_key → boolean`, not a free-form JSON blob.
- A separate `index` of embed configurations per Space. There's at most one row per Space; uniqueness is enforced at the migration (`$table->unique('space_id')`).
