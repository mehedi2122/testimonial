# Tasks: embed-builder

Eight task groups, each ≤ 2 hours, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

This change ships in a single commit on `main` at the end; the groups below are sequencing guidance for the implementer (same pattern `dashboard-analytics/tasks.md` and `space-settings/tasks.md` used).

Commit messages follow `Group 19 <verb> ...`.

---

## 1. Spec & policy skeleton

- [ ] 1.1 Create `openspec/specs/embed-builder/spec.md` and `tasks.md`
- [ ] 1.2 Add `SpacePolicy::updateEmbed(User, Space): bool` mirroring `view`/`update` — owner-only. Used by both the GET (preparing the form) and the PATCH.

## 2. FormRequest + Action

- [ ] 2.1 `App\Http\Requests\Spaces\UpdateEmbedConfigurationRequest` with rules:
    - `layout` in `masonry|carousel`
    - `dark_mode`, `animation_enabled`, `show_rating` → boolean
    - `background_color` nullable, `regex:/^#[0-9A-Fa-f]{6}$/`
    - `item_limit` integer, `min:1`, `max:50` (matches `EmbedConfiguration::MAX_ITEM_LIMIT`)
    - `field_visibility` optional array of `field_key => boolean` (only allow keys that match an existing live `space_fields` row for this Space)
- [ ] 2.2 `App\Actions\Embeds\UpdateEmbedConfigurationAction::run(Space, array): EmbedConfiguration`
    - normalize `background_color` to uppercase `#RRGGBB` or `null`
    - clamp `item_limit` (defence in depth — the FormRequest already caps it)
    - `updateOrCreate` on `space_id`
    - sync `space_fields.show_in_embed` from `field_visibility` (only `space_fields` rows for this Space; unknown keys are ignored)

## 3. Controller wiring

- [ ] 3.1 `SpaceController::embed(Space $space): Response` — keep `Gate::authorize('view', $space)`, add `load('embedConfiguration', 'fields')`, pass `embed` (the config or sensible defaults), `fields` (each as `{ key, label, show_in_embed }`), `testimonials` (small preview slice — 6 most-recent public testimonials with the API-resource whitelist applied, same as the future `embed-widget` does).
- [ ] 3.2 `SpaceController::updateEmbed(Space $space, UpdateEmbedConfigurationRequest $request): RedirectResponse` — `Gate::authorize('updateEmbed', $space)`, call the action, redirect back with `success` flash.
- [ ] 3.3 `routes/web.php` — `Route::patch('/{space}/embed', [SpaceController::class, 'updateEmbed'])->name('embed.update')`. Inside the existing `Route::middleware('auth')->prefix('spaces')` block.

## 4. Embed snippet builder (server-side)

- [ ] 4.1 New `App\Support\EmbedSnippet::for(Space, EmbedConfiguration): string` — pure function returning the HTML+JS snippet the owner copies. Shape (matching PRD §22):

      <div data-testimonial-space="{public_id}" data-style="{layout}" data-theme="{dark|light}" data-bg="{background_color}" data-limit="{item_limit}" data-show-rating="{1|0}"></div>
          <script src="{origin}/embed.js" defer></script>

    - `origin` is read from `request_root()` (the same `APP_URL` resolution `Inertia::render` uses; the public-facing widget, `embed-widget`, resolves the same way).
    - `data-theme` is `"dark"` when `dark_mode = true`, else `"light"`.

- [ ] 4.2 The page passes the rendered snippet string to the React component. The component renders it inside a `<pre>` and exposes a Copy button (clipboard API, no new dep).

## 5. Inertia page (embed.tsx)

- [ ] 5.1 Three columns on `lg+`, stacked on mobile: **Configuration** (form), **Live preview** (right), **Embed code** (full-width bottom card).
- [ ] 5.2 Form fields:
    - Layout: `<RadioGroup>` masonry | carousel
    - Dark mode: `<Switch>`
    - Animation: `<Switch>`
    - Background color: `<Input type="color">` + clear button (sets to null)
    - Item limit: `<Input type="number" min=1 max=50>`
    - Show rating: `<Switch>` (disabled with helper text when `space.rating_enabled = false`)
    - Field visibility: one `<Switch>` per `space_fields` row (label: "Show {label} on embed")
- [ ] 5.3 Live preview pane: list of up to 6 testimonial cards using the same Card primitive as `inbox.tsx`. Background uses `embed.background_color` if set, else the page's neutral. Theme switches between `dark`/`light` per `embed.dark_mode`. Layout selector toggles CSS classes between two simple layouts (`grid-cols-1` masonry-ish vs. `flex snap-x` carousel-ish). No JS library.
- [ ] 5.4 Embed code card: `<pre>` with the snippet, a **Copy** button (uses `navigator.clipboard.writeText`, with a 2-second "Copied!" flash), and a small footnote linking to a future `embed-widget` change.
- [ ] 5.5 Form is uncontrolled-with-defaults; submission is a normal Inertia `<Form>` POSTing to `PATCH /spaces/{slug}/embed` (use `method="patch"`).
- [ ] 5.6 Validation errors render under their fields (same pattern as `settings.tsx`).
- [ ] 5.7 Flash success / error Alerts (matches `inbox.tsx` and `dashboard.tsx`).

## 6. Policy test

- [ ] 6.1 `SpacePolicyTest::test_update_embed_only_owner` — owner passes, other user fails.

## 7. Action test

- [ ] 7.1 `UpdateEmbedConfigurationActionTest`:
    - happy path: creates a row when none exists
    - happy path: updates an existing row (item_limit, dark_mode, etc.)
    - clamps `item_limit` to `MAX_ITEM_LIMIT` (defence in depth)
    - normalizes `background_color` to uppercase (lowercase input → uppercase output)
    - clears `background_color` to `null` when empty string is passed
    - syncs `space_fields.show_in_embed` from `field_visibility` and ignores unknown keys
    - unknown `field_key` in `field_visibility` does not error and does not create a `space_fields` row

## 8. Feature test

- [ ] 8.1 `EmbedBuilderTest`:
    - GET 200 for owner, page is `spaces/embed`, `embed` prop reflects saved row
    - GET 200 with default `embed` shape when no `EmbedConfiguration` row exists yet (masonry, light, animation on, item_limit 12, show_rating true)
    - GET 403 for non-owner (security-gap lock continues)
    - PATCH 302 for owner — creates row
    - PATCH 302 for owner — updates existing row
    - PATCH 403 for non-owner
    - PATCH validation: invalid `layout` → 302 with errors
    - PATCH validation: `background_color = "red"` → 302 with errors (regex failure)
    - PATCH validation: `item_limit = 0` → 302 with errors
    - PATCH validation: `item_limit = 51` → 302 with errors
    - PATCH validation: unknown `field_key` in `field_visibility` does not error
    - PATCH syncs `space_fields.show_in_embed` and the GET reflects it on next load

## 9. Quality gates + commit

- [ ] 9.1 `vendor/bin/pint --parallel`
- [ ] 9.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [ ] 9.3 `npx vp check --fix`
- [ ] 9.4 `php artisan test` — target ~237 tests (216 + ~21 new across action + feature)
- [ ] 9.5 Single commit on `main`: `Group 19 embed builder`
- [ ] 9.6 Push to `https://github.com/mehedi2122/testimonial.git`

---

## Verification at each commit

```bash
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npx vp check --fix
php artisan test
```
