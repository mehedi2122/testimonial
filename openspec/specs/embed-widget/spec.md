# embed-widget Specification

## Purpose

Replace the `<script src=".../embed.js">` placeholder shipped in Group 19 with a real, public, owner-configurable testimonial widget. The owner already configures the embed at `/spaces/{slug}/embed` (layout, theme, animation, background color, item limit, rating visibility, per-field visibility). This change ships the page that _renders_ that configuration in a third-party browser, plus a tiny loader script that finds every `div[data-testimonial-space]` and mounts an iframe per node.

The widget is bounded to **publicly visible testimonials only** — the `Testimonial::scopePubliclyVisible()` scope (consent_given AND is_wall_of_love AND NOT is_hidden AND NOT soft-deleted) is the gate on every read. The widget never sees an owner's draft.

The widget is bounded to **whitelisted columns only** — `EmbedTestimonialResource` is the security boundary that prevents `email` from ever entering the projection. Per data-model §4.1, future contributors cannot `->toArray()` a Testimonial and leak an email.

The widget is bounded to **one Space per snippet**. Multiple embeds per Space is a separate (declined) change.

---

## Requirements

### Requirement: `GET /embed/{public_id}` SHALL render the iframe document

The system SHALL enforce this requirement.

The route is public (no auth, no throttle). It resolves the Space via `public_id`, throws `ModelNotFoundException` on miss (which the controller converts to a `404`), and returns `view('embed.frame', $payload)` with `Content-Type: text/html; charset=UTF-8`. The view is a standalone HTML document — no `app.blade.php` chrome, no Inertia, no React. It carries its own inline `<style>`.

The view receives the action's payload plus, optionally, query-string overrides for `?style=&theme=&limit=&show-rating=&bg=`. Unknown query values fall back to the saved config (or defaults when no row exists).

#### Scenario: happy path

- **WHEN** the iframe is loaded with a valid `public_id` and 5 public testimonials exist
- **THEN** the response is 200, `Content-Type: text/html`, the body contains 5 testimonial cards, and each card shows the submitter's name, body, and any visible custom-field values.

#### Scenario: unknown public_id

- **WHEN** the iframe is loaded with `public_id = "zzz-unknown-zzz"`
- **THEN** the response is 404 with `Content-Type: text/html`.

#### Scenario: soft-deleted Space

- **WHEN** the iframe is loaded with a `public_id` whose Space was soft-deleted after the snippet was placed
- **THEN** the response is 404. The widget never shows a partially-rendered or error state on the host site.

### Requirement: `GET /embed.js` SHALL return the loader script

The system SHALL enforce this requirement.

The route is public, no throttle. Returns `public/embed.js` with `Content-Type: application/javascript`. The script is static content — no per-request interpolation. A long-lived `Cache-Control: public, max-age=31536000` header is set so the browser caches aggressively. The script's URL in the snippet should carry a `?v=` query (set by `EmbedSnippet::for`) to invalidate the cache when the script changes.

#### Scenario: loader response shape

- **WHEN** the host page makes a request to `/embed.js`
- **THEN** the response is 200 with `Content-Type: application/javascript` and the body is the loader IIFE.

### Requirement: The action SHALL whitelist columns and exclude email from every payload

The system SHALL enforce this requirement.

`EmbedTestimonialResource::toArray(Testimonial)` returns exactly:

```php
[
    'id' => $this->id,
    'name' => $this->name,
    'testimonial' => $this->testimonial,
    'rating' => $this->rating,
    'submitted_at' => $this->submitted_at?->toIso8601String(),
    'fields' => [
        ['label' => ..., 'value' => ..., 'type' => ...],
        // only fields where spaceField.show_in_embed = true AND spaceField.deleted_at IS NULL
    ],
]
```

`email` is never read off the model in this class. The action loads Testimonials via the model's full attributes (Eloquent doesn't care), but every column that flows into the Blade view goes through `EmbedTestimonialResource`. No `->toArray()` or `->attributesToArray()` on a raw Testimonial is allowed.

#### Scenario: email is never in the payload

- **WHEN** a testimonial with `email = "private@example.com"` is loaded
- **THEN** `EmbedTestimonialResource::toArray(...)` returns an array that does not contain the key `email`.

#### Scenario: hidden fields are filtered

- **WHEN** a testimonial has values for two `space_fields`, one with `show_in_embed = true` and one with `show_in_embed = false`
- **THEN** the resource's `fields` array contains only the visible field.

### Requirement: The action SHALL only return publicly visible testimonials

The system SHALL enforce this requirement.

The query is `$space->testimonials()->publiclyVisible()->...`. The scope applies all four §15 conditions:

- `consent_given = true`
- `is_wall_of_love = true`
- `is_hidden = false`
- `deleted_at IS NULL`

Soft-deleted testimonials are excluded. Hidden / not-on-wall / unconsented testimonials are excluded. No code path in the action bypasses this scope.

#### Scenario: hidden testimonial not shown

- **WHEN** the Space has 1 visible and 1 hidden testimonial (`is_hidden = true`)
- **THEN** the payload contains 1 testimonial, the hidden one is excluded.

#### Scenario: soft-deleted testimonial not shown

- **WHEN** a testimonial was soft-deleted via the inbox
- **THEN** it does not appear in any embed payload.

### Requirement: The action SHALL use sensible defaults when no `embed_configurations` row exists

The system SHALL enforce this requirement.

When the Space has no `embed_configurations` row, the action returns the same defaults the Embed Builder uses for the empty form: `layout = masonry`, `dark_mode = false`, `animation_enabled = true`, `background_color = null`, `item_limit = 12`, `show_rating = true`. This lets a Space publish a snippet without ever opening the Embed Builder.

#### Scenario: no config row

- **WHEN** the Space has no `embed_configurations` row
- **THEN** the payload uses the defaults above.

#### Scenario: saved config

- **WHEN** the owner configured `layout = carousel`, `dark_mode = true`, `background_color = "#0F172A"`, `item_limit = 24`, `show_rating = false`
- **THEN** the payload reflects those values (and the iframe renders with those).

### Requirement: The action SHALL clamp `item_limit` to `1..MAX_ITEM_LIMIT`

The system SHALL enforce this requirement.

`EmbedConfiguration::MAX_ITEM_LIMIT` is `50`. The action clamps the saved value to that range before applying it to the query. Defence in depth — the FormRequest already enforces this on save, but the read path must not trust the column value to be in range (e.g. if a future migration sets `item_limit = 200` for an existing row).

#### Scenario: item_limit clamped above max

- **WHEN** an `embed_configurations` row has `item_limit = 1000`
- **THEN** the action returns at most `MAX_ITEM_LIMIT` (50) testimonials.

### Requirement: The frame view SHALL be a strict, standalone document

The system SHALL enforce this requirement.

`resources/views/embed/frame.blade.php` renders a `<!DOCTYPE html>` with:

- `<meta charset="utf-8">`, `<meta name="viewport" content="width=device-width, initial-scale=1">`
- Inline `<style>` — no external CSS, no `@import`, no remote fonts
- No Vite / no `@viteReactRefresh` / no `<script>` that needs bundling
- The loader has no need to fetch more JS for the iframe content

This makes the widget work inside any cross-origin iframe (no CORS for CSS, no third-party font load failures).

#### Scenario: iframe has no external dependencies

- **WHEN** the iframe HTML is fetched
- **THEN** the body contains zero `<link rel="stylesheet">` and zero `<script src="...">` tags. All styles are inline.

### Requirement: The loader SHALL mount an iframe per `div[data-testimonial-space]`

The system SHALL enforce this requirement.

The loader script runs once on `DOMContentLoaded`, finds every element with the attribute `data-testimonial-space`, and replaces it with an `<iframe>` whose `src` is computed from the element's other `data-*` attributes. The iframe's `src` uses the same origin as the loader script's own URL, so the host site doesn't need to know the app's URL.

#### Scenario: one widget on the host page

- **WHEN** the host page has one `<div data-testimonial-space="..."></div>`
- **THEN** after the script runs, the DOM contains one `<iframe>` whose `src` matches `/embed/{public_id}?...`.

#### Scenario: multiple widgets

- **WHEN** the host page has three `div[data-testimonial-space]` elements (three different Spaces)
- **THEN** the DOM contains three iframes, one per Space.

### Requirement: The widget SHALL respect the owner's `background_color` and `dark_mode` inside the iframe

The system SHALL enforce this requirement.

When `background_color` is set, the iframe's `<html>` carries an inline `style="background-color: ..."`. When `dark_mode` is true, the iframe's `<html>` carries a `data-theme="dark"` attribute, which the inline `<style>` uses to flip the card palette. When both are set, they apply together. When neither is set, the iframe uses a neutral default.

#### Scenario: dark mode

- **WHEN** `dark_mode = true`
- **THEN** the iframe `<html>` has `data-theme="dark"` and the cards use a dark palette (dark background, light text).

#### Scenario: background_color

- **WHEN** `background_color = "#0F172A"`
- **THEN** the iframe `<html>` has `style="background-color: #0F172A"`.

#### Scenario: both unset

- **WHEN** both `dark_mode = false` and `background_color = null`
- **THEN** the iframe uses a neutral default (white background, dark text).

### Requirement: The widget SHALL honor `show_rating` and per-field visibility

The system SHALL enforce this requirement.

When `show_rating` is true, each card with a `rating` renders the 1–5 star row. When false, the row is omitted entirely (not just hidden via CSS — the markup doesn't exist).

Custom-field rows render only for `space_fields` where `show_in_embed = true` AND `deleted_at IS NULL`. A field value whose field is hidden is dropped at the resource layer (not just visually hidden).

#### Scenario: show_rating false hides rating row

- **WHEN** `show_rating = false` and a testimonial has `rating = 5`
- **THEN** the iframe body does not contain any `<span class="star">` markup for that card.

#### Scenario: hidden field value not rendered

- **WHEN** a testimonial has a `company_name` value but the Space's `space_fields.company_name.show_in_embed = false`
- **THEN** the iframe body for that card does not include the company row.

### Requirement: The layout SHALL switch between masonry and carousel via CSS

The system SHALL enforce this requirement.

The iframe's root container has `class="layout-{layout}"`. The inline `<style>` defines:

- `.layout-masonry` → CSS grid `grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))`
- `.layout-carousel` → horizontal scroll, `display: flex; overflow-x: auto; scroll-snap-type: x mandatory;`

Cards inside the carousel get `scroll-snap-align: start`. No JavaScript is needed.

#### Scenario: masonry layout

- **WHEN** the saved config has `layout = "masonry"` (or the loader passes `?style=masonry`)
- **THEN** the iframe root has `class="layout-masonry"` and the cards lay out as a responsive grid.

#### Scenario: carousel layout

- **WHEN** `layout = "carousel"`
- **THEN** the iframe root has `class="layout-carousel"` and the cards lay out as a horizontally scrollable snap container.

### Requirement: The widget SHALL render an empty state when no public testimonials exist

The system SHALL enforce this requirement.

When the Space has zero publicly visible testimonials, the iframe renders a short "No testimonials yet" message instead of an empty container. The host site never sees a broken widget.

#### Scenario: no testimonials

- **WHEN** the Space has 0 publicly visible testimonials
- **THEN** the iframe body contains the empty-state copy, no card markup.

### Requirement: Authorization SHALL NOT be required

The system SHALL enforce this requirement.

Both routes (`/embed.js`, `/embed/{public_id}`) are public. No `auth` middleware, no `verified` middleware, no per-Space gate. The public-visibility scope is the only access control, and it operates per-row inside the action.

#### Scenario: unauthenticated request

- **WHEN** an unauthenticated request hits `/embed/{public_id}`
- **THEN** the response is the iframe document (or 404 if the Space is unknown / deleted).

---

### Requirement: The widget SHALL honor `animation_enabled`

When `animation_enabled = false` (saved config, or `?animation=0` from the loader's `data-animation="0"`), the frame renders `<html class="no-animation">` and cards appear without the fade-in. The snippet carries `data-animation` so the loader forwards it.

#### Scenario: animation disabled

- **GIVEN** an `embed_configurations` row with `animation_enabled = false`
- **WHEN** `GET /embed/{public_id}`
- **THEN** the HTML contains `class="no-animation"`

#### Scenario: animation enabled

- **GIVEN** `animation_enabled = true`
- **WHEN** `GET /embed/{public_id}`
- **THEN** the HTML does not contain `class="no-animation"`

### Requirement: The iframe SHALL size itself to its content

The frame posts `{ type: 'testimonial-embed:resize', height }` to its parent whenever `<body>` resizes. The loader accepts the message only from its own origin and from an iframe it mounted, and sets that iframe's height. The frame measures `<body>`, not `documentElement`, so the iframe can shrink as well as grow. The background color is applied to the `--bg` variable `<body>` paints, so it is visible.

#### Scenario: tall masonry on a phone

- **GIVEN** 12 public testimonials and a 360px-wide host container
- **WHEN** the host page loads the snippet
- **THEN** the iframe height matches the one-column content and no card is clipped

#### Scenario: short carousel

- **GIVEN** a carousel whose content is shorter than the 480px initial height
- **WHEN** the frame reports its height
- **THEN** the iframe shrinks to the content height
