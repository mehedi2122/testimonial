# Tasks: embed-widget

Seven task groups, each ≤ 2 hours, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

This change ships in a single commit on `main` at the end; the groups below are sequencing guidance for the implementer (same pattern as `embed-builder/tasks.md`).

Commit messages follow `Group 20 <verb> ...`.

---

## 1. Spec & resource skeleton

- [ ] 1.1 Create `openspec/specs/embed-widget/spec.md` and `tasks.md`
- [ ] 1.2 Create `app/Http/Resources/EmbedTestimonialResource.php` — single public `toArray()` that returns only `{ id, name, testimonial, rating, submitted_at, fields }`. `email` is never in the result. `fields` is computed from `values->spaceField` filtered by `show_in_embed = true` and where the field row is not soft-deleted.

## 2. Action

- [ ] 2.1 `App\Actions\Embeds\BuildEmbedWidgetPayloadAction::build(string $publicId): array`
    - resolve Space via `Space::query()->where('public_id', $publicId)->whereNull('deleted_at')->with('embedConfiguration')->firstOrFail()` (throws `ModelNotFoundException` on miss)
    - normalize config to defaults when row absent (`masonry`, light, animation on, null bg, item_limit 12, show_rating true)
    - clamp `item_limit` to `1..EmbedConfiguration::MAX_ITEM_LIMIT`
    - fetch testimonials via `$space->testimonials()->publiclyVisible()->with(['values.spaceField' => fn ($q) => $q->where('show_in_embed', true)->whereNull('deleted_at')])->limit($itemLimit)->get()`
    - map each through `EmbedTestimonialResource`
    - return array with shape: `{ space_name, layout, dark_mode, background_color, animation_enabled, show_rating, testimonials: [...] }`

## 3. Controller + routes

- [ ] 3.1 `App\Http\Controllers\Embed\EmbedWidgetController`:
    - `frame(string $publicId, Request $request, BuildEmbedWidgetPayloadAction $action): View` — calls action; query string `?style=&theme=&limit=&show-rating=&bg=` may OVERRIDE the saved config (lets the loader pass per-instance overrides). Unknown query values fall back to saved config. Returns `view('embed.frame', $payload)`.
    - `loader(): Response` — returns `response()->file(public_path('embed.js'))` with `Content-Type: application/javascript` and a long-lived `Cache-Control` header (the file is content-hashed in its own URL via `?v=`).
- [ ] 3.2 `routes/web.php` — both routes are public (no `auth` middleware, no throttle for MVP):
    - `Route::get('embed.js', [EmbedWidgetController::class, 'loader'])->name('embed.loader');`
    - `Route::get('embed/{publicId}', [EmbedWidgetController::class, 'frame'])->name('embed.frame');`

## 4. Blade frame view

- [ ] 4.1 `resources/views/embed/frame.blade.php` — standalone `<!DOCTYPE html>` with `<meta charset>`, `<meta viewport>`, inline `<style>` (no external CSS). Renders:
    - Header strip: `{space_name}` + tiny "by [your app name]" attribution
    - Layout container: `class="layout-{layout}"` toggles CSS between masonry (CSS grid `grid-template-columns: repeat(auto-fill, minmax(280px, 1fr))`) and carousel (horizontal scroll with snap)
    - Per-card: avatar initials, name, rating row (when `show_rating && rating !== null`), testimonial body, custom field rows (when present)
    - Empty state: "No testimonials yet" copy
- [ ] 4.2 Inline `<style>` covers both themes (light/dark) via a `[data-theme="dark"]` attribute on `<html>`. The host page cannot affect iframe styling.
- [ ] 4.3 Background color: when `background_color` is set, applied as inline `style` on `<html>`; otherwise the light/dark default.

## 5. Loader script

- [ ] 5.1 `public/embed.js` (~30 lines):
    - IIFE, strict mode
    - on `DOMContentLoaded` (or immediately if already loaded), find all `[data-testimonial-space]`
    - for each, create `<iframe>` and copy attributes (`data-style`, `data-theme`, `data-bg`, `data-limit`, `data-show-rating`) onto the iframe URL as query string params
    - iframe attributes: `loading="lazy"`, `title="Testimonials"`, `style="width:100%;border:0;display:block"`, fixed height (e.g. `480px`)
    - replace the original `<div>` with the iframe
- [ ] 5.2 The iframe's `src` is computed **relative** to the loader's own URL. The loader reads `document.currentScript.src` to derive the origin, so the script works regardless of where it's hosted (it points at the same `/embed/{public_id}` endpoint).

## 6. Action tests

- [ ] 6.1 `BuildEmbedWidgetPayloadActionTest` (~11 cases):
    - happy path: 5 public testimonials, returns 5 mapped items in the right order (favorites first, then submitted_at desc)
    - unknown public_id throws `ModelNotFoundException`
    - soft-deleted Space throws `ModelNotFoundException`
    - excludes hidden testimonials
    - excludes not-on-wall testimonials (`is_wall_of_love = false`)
    - excludes unconsented testimonials (`consent_given = false`)
    - respects `item_limit`
    - **never includes `email` in any testimonial payload** (security check)
    - respects `space_fields.show_in_embed` (hidden field values not in `fields` array)
    - uses defaults when no `embed_configurations` row exists
    - clamps item_limit to MAX_ITEM_LIMIT when config exceeds it

## 7. Feature tests

- [ ] 7.1 `EmbedWidgetControllerTest` (~7 cases):
    - `GET /embed/{public_id}` returns 200 with HTML
    - `GET /embed/{public_id}` returns 404 for unknown public_id
    - `GET /embed/{public_id}` returns 404 for soft-deleted Space
    - `GET /embed.js` returns 200 with `Content-Type: application/javascript`
    - `GET /embed/{public_id}` HTML body contains testimonial text
    - `GET /embed/{public_id}?style=carousel` HTML body contains `layout-carousel` class
    - `GET /embed/{public_id}?show-rating=0` HTML body does NOT contain the rating row markup

## 8. Quality gates + commit

- [ ] 8.1 `vendor/bin/pint --parallel`
- [ ] 8.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [ ] 8.3 `npx vp check --fix`
- [ ] 8.4 `php artisan test` — target ~262 tests (247 + ~15 new)
- [ ] 8.5 Single commit on `main`: `Group 20 embed widget`
- [ ] 8.6 Push to `https://github.com/mehedi2122/testimonial.git`

---

## Verification at each commit

```bash
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npx vp check --fix
php artisan test
```

## Manual smoke test

```bash
php artisan serve --host=0.0.0.0
# Loader:
curl -sI http://127.0.0.1:8000/embed.js
# Frame:
SEED=$(php artisan tinker --execute='echo App\Models\Space::factory()->create()->public_id;' 2>/dev/null)
curl -sI "http://127.0.0.1:8000/embed/$SEED"
# Body should contain "<!DOCTYPE html>" and "Testimonials"
curl -s "http://127.0.0.1:8000/embed/$SEED" | head -5
```
