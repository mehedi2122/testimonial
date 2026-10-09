# public-wall-of-love — Proposal

## Why

Group 20 (`embed-widget`) shipped the third-party widget: `/embed/{public_id}` iframe + `/embed.js` loader. The owner can paste that onto an external site and render their testimonials there.

The owner has **no first-party surface** to share a Wall of Love from inside our own app. PRD §18 names "Wall of Love" as a publish target but only defines the owner-side toggle — it does not define a public page. Today there is no `/wall/{slug}` route, no way to share "look at our testimonials" on Twitter/LinkedIn/email without copying an `<iframe>` snippet.

This change ships a public, unauthenticated, full Inertia page at `GET /wall/{slug}`. The owner pastes a single URL on their marketing channels; visitors see a header, hero copy, and a responsive masonry grid of the Space's publicly visible testimonials (favorites pinned, then newest first). It is **not** the embed — different surface, different audience, different routing key (slug vs. immutable `public_id`).

## What changes

1. **`GET /wall/{slug}`** — public, no auth, no throttle. Resolves the Space via `slug`, throws `ModelNotFoundException` on miss (controller converts to `404`), and returns `Inertia::render('public/wall', $payload)`. The page is part of our app — full `app.blade.php` chrome, full Inertia stack, not a Blade-only iframe document like the embed.

2. **`App\Actions\Public\ShowPublicWallAction`** owns the read path. Resolves `slug` → Space, queries testimonials through `scopePubliclyVisible()` (favorites first, then `submitted_at` desc, all four §15 conditions applied), eager-loads `values.spaceField` filtered by `show_in_embed = true`, and runs each row through `EmbedTestimonialResource`. No `embed_configurations` row is read; the wall has a single canonical layout. No item cap — the wall shows the **whole public set** (the embed is the curated, capped surface; the wall is the canonical mirror).

3. **`App\Http\Resources\EmbedTestimonialResource`** is extended by one field: `is_favorite: bool`. This is PII-safe — it's an internal ordering flag the inbox already exposes. With this in the resource, both the embed iframe and the wall can render a `Pin` icon for starred rows without breaking the data-model §4.1 "every column flows through the resource" rule. No `->toArray()` on a raw `Testimonial` anywhere in the wall code path.

4. **`resources/js/pages/public/wall.tsx`** — Inertia page mirroring `submit.tsx`'s `<section className="... space-theme-{theme}">` chrome (no `AppShell`, no nav — public surface). Header with `space.title` + `space.subtitle` + count. Responsive masonry (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`). Per-card: `Avatar`+`AvatarFallback` initials, submitter name, optional 5-star rating row, `whitespace-pre-line` body, `<dl>` of field values, top-right `Pin` icon when `is_favorite`. Empty state card when `count === 0`.

5. **No new dependency, no new column, no migration**. Reuses `space_fields.show_in_embed` (per data-model §3.6: "Where Visibility Toggles Live") for field visibility on the wall too. A separate `show_in_wall` is explicitly declined — one flag covers both surfaces.

## Impact

- **Schema**: none. Same `testimonials`, `spaces`, `space_fields`. One new key (`is_favorite`) on `EmbedTestimonialResource::toArray` — additive, non-breaking for the embed.
- **Routes**: one new public route (`/wall/{slug}`). Outside the `auth` middleware group. No throttle (read-only GET).
- **Existing tests**: must continue to pass (268 currently green). The new `is_favorite` resource key is additive; no embed test reads the resource's full shape (they only assert `email` is absent).
- **Bundle**: zero new dependencies. Inertia + the existing shadcn components (`Avatar`, `Card`, `Badge`).
- **Public surface**: another unauthenticated GET. Same security model as `/s/{public_id}` and `/embed/{public_id}` — the §15 `scopePubliclyVisible()` is the gate.

## Out of scope (explicit)

- The embed widget itself (`/embed/{public_id}` + `/embed.js`) — shipped in Group 20.
- The `<div data-testimonial-space>` snippet — already shipped in Group 19.
- A new `show_in_wall` toggle on `space_fields`. Reuse `show_in_embed`. If owners later need finer control, that's a separate change.
- Wall-specific theming (dark mode, layout choice). The wall is one canonical responsive layout; configuration is the embed's job (data-model §3.6).
- Pagination / "load more" on the wall. The full public set fits on one page for MVP. Pagination is a follow-up.
- An RSS / JSON feed of wall entries. Separate change if requested.
- A "Submit your testimonial" CTA pointing at `/s/{public_id}`. Explicitly add later, not MVP — the wall is a shareable read surface.
- Owner analytics ("who viewed the wall"). No telemetry on read endpoints in MVP.
- `billing-stripe`, `email-notifications` — separate OpenSpec changes.
