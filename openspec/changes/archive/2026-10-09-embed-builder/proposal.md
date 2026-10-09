# embed-builder — Proposal

## Why

The owner already gets `/spaces/{slug}/dashboard`, `/inbox`, and `/settings` pages that help them **collect** and **manage** testimonials. The next conversion moment — "publishes them externally" — happens on `/spaces/{slug}/embed`, but today that page is a placeholder with a hard-coded snippet. PRD §19–§23 promise an Embed Builder: a page where the owner picks a Space (it's already the only one in scope per data-model §7.5), configures layout + dark mode + visibility toggles, sees a **live preview**, and **copies the embed code**.

The data model is already done. The `embed_configurations` table, the `EmbedConfiguration` model, the `EmbedLayout` enum, the `Space::embedConfiguration()` HasOne, and the placeholder Inertia page all shipped in earlier groups. What's missing is the **owner-facing UI** and the **write path** that turns a form submission into an `embed_configurations` row.

This change is the owner-facing authoring tool. The public-facing embed widget — the actual `<script>` consumed by external sites — is a separate change (`embed-widget`, Group 20) that reads the same `embed_configurations` row.

## What changes

1. **`SpaceController::embed()`** now also loads (or `firstOrCreate`s) the Space's `EmbedConfiguration` and passes the full form payload to the Inertia page. The placeholder page gets replaced.
2. **`App\Actions\Embeds\UpdateEmbedConfigurationAction::run(Space, array): EmbedConfiguration`** owns the write path. Validates input, normalizes `background_color` (nullable 7-char `#RRGGBB`), clamps `item_limit` to `1..MAX_ITEM_LIMIT`, and `updateOrCreate`s the row. Controllers do not write directly.
3. **`App\Http\Requests\Spaces\UpdateEmbedConfigurationRequest`** holds the FormRequest rules. The action trusts the validated array.
4. **`resources/js/pages/spaces/embed.tsx`** becomes a 3-pane builder:
    - **Left**: configuration form (layout radio, dark mode toggle, animation toggle, background color picker, `item_limit` number input, rating visibility toggle, "show/hide profile photo" — actually, by data-model §3.5, profile photo is a `space_fields` row, not an `embed_configurations` toggle; the field-level `show_in_embed` is the actual knob. PRD §20 lists "show/hide profile photo" but the schema's decision is that the per-field `show_in_embed` handles all non-rating fields. The change reconciles this by reading `space_fields` rows and showing one row-toggle per field. This is a spec, not a code-deferral).
    - **Right**: live preview that re-renders the (currently in-memory) testimonial card list as the form changes. Pure CSS preview, no new JS dep.
    - **Bottom**: the embed snippet (read-only `<pre>`) and a **Copy** button.
5. **FormRequest rules** gate the form. Validation errors render inline next to the offending field.
6. **Authorization** is unchanged: `SpacePolicy::view` (already enforced on the GET) is reused via a new `update` route under the same policy. A new `updateEmbed(User, Space)` method is added to `SpacePolicy` (mirrors `update`) and called via `Gate::authorize` in the controller.
7. **Tests**: a `UpdateEmbedConfigurationActionTest` for the action (validation, clamping, color normalization, create vs. update) and a `EmbedBuilderTest` for the page (GET 200 for owner, GET 403 for non-owner, POST creates, POST updates, POST 403 for non-owner, validation errors re-render with `errors`).

## Impact

- **Schema**: none. The table shipped in Group 12 (`2026_10_06_070004_create_embed_configurations_table.php`).
- **Routes**: one new `PATCH /spaces/{space}/embed` (and a `PUT` alias per Laravel 11 convention; or just `POST _method=PATCH` via the Inertia helper). Single route, single controller method.
- **Public surface**: untouched. The widget rendering is `embed-widget` (Group 20). This change only mutates the owner's own row.
- **Bundle**: no new JS dependency. The preview uses the same shadcn primitives already shipped (Card, Switch, Input, Label, RadioGroup).
- **Existing tests**: must continue to pass (216 currently green).

## Out of scope (explicit)

- The actual external-site widget (`<script src="...">` and its loader) — `embed-widget` (Group 20).
- `public-wall-of-love` (Group 21) — the dedicated public Wall-of-Love page on the marketing site.
- Billing (`billing-stripe`, Group 22) — the Pro-plan upgrade CTA inside the embed page stays informational, matching the dashboard banner pattern.
- Multiple embeds per Space. The data-model §7.5 decision explicitly says "one embed per Space, addressed by `public_id`." Multi-embed is a separate schema and snippet format change.
- Custom CSS / theming beyond `background_color` and `dark_mode`. The PRD says "Keep the configuration minimal." (PRD §20).
- `display_options` JSON column. Decision log #10 dropped it; per-field `show_in_embed` and `embed_configurations.show_rating` cover the requirement.
- Analytics on embed impressions / clicks. No event collection in MVP.
- A real-time preview that hits the API. The preview re-renders on every form change in the browser using the same testimonial JSON the page already has. No new endpoint.
