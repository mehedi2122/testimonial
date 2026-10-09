# Tasks: public-wall-of-love

Two task groups, ≤ 2 hours each, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

This change ships in a single commit on `main` at the end; the groups below are sequencing guidance for the implementer (same pattern as `embed-widget/tasks.md`).

Commit messages follow `Group 21 <verb> ...`.

---

## 1. Spec & resource extension

- [x] 1.1 Create `openspec/specs/public-wall-of-love/spec.md` and `tasks.md`
- [x] 1.2 Add `is_favorite: bool` to `App\Http\Resources\EmbedTestimonialResource::toArray()`. The key is PII-safe (internal ordering flag, already exposed in the inbox scope), additive for existing embed consumers.

## 2. Action

- [x] 2.1 `App\Actions\Public\ShowPublicWallAction`:
    - `resolve(string $slug): Space` — `Space::query()->where('slug', $slug)->whereNull('deleted_at')->firstOrFail()` (throws `ModelNotFoundException` on miss)
    - `buildPayload(Space $space): array` — fetches testimonials via `$space->testimonials()->publiclyVisible()->with(['values.spaceField' => fn ($q) => $q->where('show_in_embed', true)->whereNull('deleted_at')])->get()`, maps each through `EmbedTestimonialResource`, returns `{ space: {name, title, subtitle, theme}, testimonials: [...], count: int }`

## 3. Controller + route

- [x] 3.1 `App\Http\Controllers\Public\PublicWallController`:
    - `show(string $slug, ShowPublicWallAction $action): Response` — calls `$action->resolve($slug)`, then `Inertia::render('public/wall', $action->buildPayload($space))`. Catches `ModelNotFoundException` and `abort(404)`.
- [x] 3.2 `routes/web.php` — public route, no `auth` middleware, no throttle:
    - `Route::get('wall/{slug}', [PublicWallController::class, 'show'])->name('public.wall.show');`

## 4. Inertia page

- [x] 4.1 `resources/js/pages/public/wall.tsx`:
    - `<section className="mx-auto max-w-6xl space-y-8 p-6 space-theme-{theme}">` chrome (mirrors `submit.tsx`)
    - Header: `<h1 className="text-4xl font-semibold">{title}</h1>`, optional subtitle, count line
    - Empty state: card with "No testimonials yet" copy
    - Grid: `grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3` with `<TestimonialCard>` per row
    - `<TestimonialCard>`: `Avatar`+`AvatarFallback` (initials), name, optional rating row, body with `whitespace-pre-line`, `<dl>` of field values, top-right `Pin` icon when `is_favorite`
    - No `<form>`, no admin actions, no email link

## 5. Action tests

- [x] 5.1 `tests/Feature/Public/ShowPublicWallActionTest.php`:
    - `resolve` returns Space for valid slug
    - `resolve` throws `ModelNotFoundException` for unknown slug
    - `resolve` throws `ModelNotFoundException` for soft-deleted Space
    - `buildPayload` returns only `scopePubliclyVisible` rows (excludes hidden / not-on-wall / unconsented / soft-deleted)
    - `buildPayload` returns favorites first then `submitted_at` desc
    - `buildPayload` payload does NOT contain the `email` key on any testimonial (security check)
    - `buildPayload` excludes `space_fields` with `show_in_embed = false`
    - empty Space returns `{count: 0, testimonials: []}`

## 6. Feature tests

- [x] 6.1 `tests/Feature/Public/PublicWallControllerTest.php`:
    - `GET /wall/{slug}` returns 200 with HTML for a valid slug
    - `GET /wall/{slug}` returns 404 for an unknown slug
    - `GET /wall/{slug}` returns 404 for a soft-deleted Space
    - HTML body contains the submitter name and testimonial text when present
    - HTML body contains the empty-state copy when no public testimonials exist
    - HTML body NEVER contains the `email` key for any row (mirrors embed-widget test)

## 7. Quality gates + commit

- [x] 7.1 `vendor/bin/pint --parallel`
- [x] 7.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [x] 7.3 `npx vp check --fix`
- [x] 7.4 `php artisan test` — target ~278 tests (268 + ~10 new)
- [ ] 7.5 Single commit on `main`: `Group 21 public wall of love` — _skipped: working copy is not a git repository (2026-10-09)_
- [ ] 7.6 Push to `https://github.com/mehedi2122/testimonial.git` — _skipped: working copy is not a git repository (2026-10-09)_

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
# In another shell:
SLUG=$(php artisan tinker --execute='echo App\Models\Space::factory()->create(["slug" => "test-wall"])->slug;')
curl -sI "http://127.0.0.1:8000/wall/$SLUG" | head -3
# Expected: 200, Content-Type: text/html
curl -s "http://127.0.0.1:8000/wall/$SLUG" | grep -i 'wall of love'
```
