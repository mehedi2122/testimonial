# embed-widget — Proposal

## Why

Group 19 shipped the owner-facing Embed Builder (`/spaces/{slug}/embed`) and the copyable snippet:

```html
<div
    data-testimonial-space="{public_id}"
    data-style="..."
    data-theme="..."
    ...
></div>
<script src=".../embed.js" defer></script>
```

**The `<script>` is a placeholder.** It doesn't exist yet. Today, pasting the snippet onto an external site does nothing. PRD §23 commits us to a widget that works without authentication, only displays publicly visible testimonials, respects the user's configuration, and works on desktop and mobile.

This change ships the public-side widget. It's the missing half of the embed story: the owner authors a configuration, and now an external site can render it.

## What changes

1. **`GET /embed.js`** — public, no auth, no throttle. Returns the loader script (`Content-Type: application/javascript`). The script reads its own `<script src>` attribute to compute the origin, finds every `div[data-testimonial-space]` on the page, and replaces each with an `<iframe>`. ~30 lines of vanilla JS, no bundler, no framework.

2. **`GET /embed/{public_id}`** — public, no auth. Returns a standalone HTML document for an iframe. The document renders testimonials per the Space's `embed_configurations` row (or sensible defaults when none exists). Built with `view('embed.frame', [...])` (Blade, not Inertia — the iframe is its own document with no app chrome). Card styling is inline so it works inside a strict sandbox.

3. **`App\Actions\Embeds\BuildEmbedWidgetPayloadAction`** owns the read path. Resolves the `public_id`, loads the Space + config (or defaults), queries `testimonials` through `scopePubliclyVisible()`, eager-loads `values.spaceField` filtered by `show_in_embed = true`, and runs each row through `EmbedTestimonialResource`. The query follows the data-model.md §4.1 pattern: explicit column list, `email` never enters the projection.

4. **`App\Http\Resources\EmbedTestimonialResource`** is the security gate for column selection. `toArray()` whitelists `id`, `name`, `testimonial`, `rating`, `submitted_at`. Per data-model.md §4.1: "The embed payload must go through an Eloquent API Resource that whitelists fields, never `Testimonial::find()->toArray()`." This class exists so a future contributor cannot `->toArray()` a Testimonial and accidentally leak `email`.

5. **No new dependency**. No npm package, no PHP package. Pure Blade + vanilla JS.

## Impact

- **Schema**: none. Same `embed_configurations` table, same `space_fields.show_in_embed`, same `testimonials` columns.
- **Routes**: two new public routes (`/embed.js`, `/embed/{public_id}`). Both outside the `auth` middleware group.
- **Public surface** (what external sites see): a single iframe document + a tiny loader. No API key, no auth handshake.
- **Existing tests**: must continue to pass (247 currently green).
- **Bundle**: zero JS added to our app bundle. The loader script ships in `public/embed.js` and is **not** processed by Vite.

## Out of scope (explicit)

- The Embed Builder itself (`/spaces/{slug}/embed` owner-facing UI) — already shipped in Group 19.
- The `<div data-testimonial-space>` snippet format — already shipped in Group 19 via `EmbedSnippet`. The widget just consumes it.
- Analytics on embed impressions / clicks — no event collection in MVP.
- Iframe auto-resize via `postMessage` — use a sensible fixed initial height. Auto-resize is a follow-up.
- CSP headers / sandbox attributes on the iframe — separate `csp-headers` change.
- Multiple embeds per Space (data-model §7.5: declined). One embed per Space, addressed by `public_id`.
- Custom CSS / per-field theming beyond what `embed_configurations` says. PRD §20 says "Keep the configuration minimal."
- A `display_options` JSON column. The form already uses typed `field_visibility` instead.
- `billing-stripe`, `public-wall-of-love`, `email-notifications` — separate OpenSpec changes.
