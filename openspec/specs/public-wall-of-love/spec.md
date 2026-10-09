# public-wall-of-love Specification

## Purpose

Ship a public, unauthenticated, full-page view of a Space's Wall of Love at `/wall/{slug}`. The owner shares this URL on marketing channels (Twitter, LinkedIn, email signatures) and visitors see the Space's hero copy plus a responsive masonry grid of publicly visible testimonials (favorites pinned, then newest first).

This is **not** the embed widget. Different audience (visitors of our own app vs. visitors of a third-party site), different routing key (owner-editable `slug` vs. immutable `public_id`), different rendering stack (Inertia + full `app.blade.php` chrome vs. iframe-only Blade document).

The wall and the embed share two things by design:

1. **The read gate** is the same `Testimonial::scopePubliclyVisible()` scope. Both surfaces can never show a draft, a hidden row, an unconsented submission, or a soft-deleted row. Ben Fischer (§1 of the data model) stays off the wall forever.
2. **The column projection** is the same `EmbedTestimonialResource` so `email` cannot enter either surface. The wall adds one extra key — `is_favorite` — because the inbox already exposes it as an ordering flag and the wall needs to render a pin.

The wall does **not** consume the `embed_configurations` row. It has a single canonical responsive layout. Configuration (dark mode, layout choice, item limit) is the embed's job — the wall is the canonical mirror of the Space's full public testimonial set, no item cap.

---

## Requirements

### Requirement: `GET /wall/{slug}` SHALL render the public Wall of Love page

The system SHALL enforce this requirement.

The route is public (no auth, no throttle). It resolves the Space via `slug` (NOT `public_id` — see §3.2 of the data model for the two-identifier rationale), throws `ModelNotFoundException` on miss (which the controller converts to a `404`), and returns `Inertia::render('public/wall', $payload)`. The page renders inside the standard `app.blade.php` chrome with Vite, so it shares the same look-and-feel as the rest of the app.

The page receives the action's payload: `{ space: {name, title, subtitle, theme}, testimonials: [...], count: int }`. The `theme` value drives a `space-theme-{theme}` class on the page's root `<section>`, mirroring `submit.tsx`.

#### Scenario: happy path

- **WHEN** an unauthenticated request hits `/wall/{slug}` for a Space with 5 public testimonials
- **THEN** the response is 200, `Content-Type: text/html`, the body contains 5 testimonial cards, and each card shows the submitter's name, body, and any visible custom-field values.

#### Scenario: unknown slug

- **WHEN** the request hits `/wall/zzz-unknown-zzz`
- **THEN** the response is 404 with `Content-Type: text/html`.

#### Scenario: soft-deleted Space

- **WHEN** the request hits `/wall/{slug}` for a Space that was soft-deleted after the URL was shared
- **THEN** the response is 404. The wall never shows a partially-rendered or "this Space no longer exists" page — it just 404s, same as the embed iframe.

### Requirement: The action SHALL only return publicly visible testimonials

The system SHALL enforce this requirement.

The query is `$space->testimonials()->publiclyVisible()->...`. The scope applies all four §15 conditions: `consent_given = true`, `is_wall_of_love = true`, `is_hidden = false`, `deleted_at IS NULL`. Soft-deleted testimonials are excluded. Hidden / not-on-wall / unconsented testimonials are excluded. No code path in the action bypasses this scope.

The scope's natural ordering is preserved: `is_favorite DESC, submitted_at DESC` — favorites pinned to the top, then newest-first within each tier.

#### Scenario: hidden testimonial not shown

- **WHEN** the Space has 1 visible and 1 hidden testimonial (`is_hidden = true`)
- **THEN** the payload contains 1 testimonial, the hidden one is excluded.

#### Scenario: soft-deleted testimonial not shown

- **WHEN** a testimonial was soft-deleted via the inbox
- **THEN** it does not appear on the wall.

#### Scenario: favorites pinned

- **WHEN** the Space has 3 testimonials, the middle one is `is_favorite = true`
- **THEN** that testimonial renders first in the masonry grid; the other two follow in `submitted_at` desc order.

### Requirement: The action SHALL whitelist columns and exclude email from every payload

The system SHALL enforce this requirement.

`EmbedTestimonialResource::toArray(Testimonial)` returns exactly:

```php
[
    'id' => $this->id,
    'name' => $this->name,
    'testimonial' => $this->testimonial,
    'rating' => $this->rating,
    'submitted_at' => $this->submitted_at->toIso8601String(),
    'is_favorite' => (bool) $this->is_favorite,
    'fields' => [
        ['label' => ..., 'value' => ..., 'type' => ...],
        // only fields where spaceField.show_in_embed = true AND spaceField.deleted_at IS NULL
    ],
]
```

`email` is never read off the model in this class. Every column that flows into the Inertia page goes through `EmbedTestimonialResource`. No `->toArray()` or `->attributesToArray()` on a raw `Testimonial` is allowed in the wall code path. The resource is the single security gate for all public projections, not just the embed iframe.

#### Scenario: email is never in the payload

- **WHEN** a testimonial with `email = "private@example.com"` is loaded
- **THEN** `EmbedTestimonialResource::toArray(...)` returns an array that does not contain the key `email`.

#### Scenario: hidden fields are filtered

- **WHEN** a testimonial has values for two `space_fields`, one with `show_in_embed = true` and one with `show_in_embed = false`
- **THEN** the resource's `fields` array contains only the visible field.

### Requirement: The wall SHALL reuse `space_fields.show_in_embed` for field visibility

The system SHALL enforce this requirement.

The wall does NOT introduce a new `show_in_wall` column. The existing `show_in_embed` flag on `space_fields` controls field visibility on both the embed iframe and the wall page. The two surfaces share a single toggle. The wall's action pre-filters `values.spaceField` at SQL level (`where('show_in_embed', true)->whereNull('deleted_at')`) and the resource re-filters in PHP. Defence in depth.

#### Scenario: hidden field not shown on wall

- **WHEN** a testimonial has a `company_name` value but `space_fields.company_name.show_in_embed = false`
- **THEN** the wall's card for that testimonial does not include a company row.

### Requirement: The wall SHALL render an empty state when no public testimonials exist

The system SHALL enforce this requirement.

When the Space has zero publicly visible testimonials, the wall renders a short "No testimonials yet" message inside a `Card` instead of an empty grid. The page still renders header + hero copy. A shared URL never looks broken.

#### Scenario: no testimonials

- **WHEN** the Space has 0 publicly visible testimonials
- **THEN** the page body contains the empty-state copy, no card markup.

### Requirement: The wall SHALL show a Pin icon for favorited testimonials

The system SHALL enforce this requirement.

When a testimonial's `is_favorite = true`, the card renders a `Pin` icon at the top-right. The icon is the same as the inbox "Starred" badge — visual continuity between owner and public surfaces. The flag comes from `EmbedTestimonialResource::is_favorite`; no extra query.

#### Scenario: favorited card shows pin

- **WHEN** a testimonial has `is_favorite = true`
- **THEN** its wall card includes a `Pin` icon and an `aria-label` indicating "Pinned to top".

#### Scenario: non-favorited card omits pin

- **WHEN** a testimonial has `is_favorite = false`
- **THEN** its wall card does not include the `Pin` icon.

### Requirement: The wall SHALL use a single responsive masonry layout

The system SHALL enforce this requirement.

The wall does NOT consume `embed_configurations.layout`. It uses one canonical layout: a CSS grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3` that adapts from one column on phones to three on desktop. No carousel, no toggle, no JS — pure CSS. The page is responsive and works on desktop and mobile without configuration (PRD §23 requirement, applied to the wall surface).

#### Scenario: 3-up grid on desktop

- **WHEN** the viewport is `lg` or wider
- **THEN** the testimonials render in a 3-column grid.

#### Scenario: 1-up on mobile

- **WHEN** the viewport is mobile (default `< sm`)
- **THEN** the testimonials stack to a single column.

### Requirement: The wall SHALL show no item cap

The system SHALL enforce this requirement.

The wall renders the Space's **full public set**. There is no `item_limit` on the wall's read path. (The embed is the curated, capped surface — the wall is the canonical mirror.) Pagination is a follow-up change.

#### Scenario: many testimonials

- **WHEN** the Space has 100 public testimonials
- **THEN** the page renders all 100. (Performance tuning is a follow-up; the data path is correct.)

### Requirement: Authorization SHALL NOT be required

The system SHALL enforce this requirement.

The route is public. No `auth` middleware, no `verified` middleware, no per-Space gate. The `scopePubliclyVisible()` is the only access control, and it operates per-row inside the action.

#### Scenario: unauthenticated request

- **WHEN** an unauthenticated request hits `/wall/{slug}`
- **THEN** the response is the wall page (or 404 if the slug is unknown / the Space is soft-deleted).

---
