# Tasks: space-settings

Eleven task groups, each ≤ 2 hours, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

Each group ends with a green local check before the next one starts. Commit messages follow `Group 17 <verb> ...`.

This change ships in a single commit on `main` at the end; the groups below are sequencing guidance for the implementer (the same pattern `public-testimonial-submission/tasks.md` used).

---

## 1. Spec & helper extraction

- [x] 1.1 Create `openspec/specs/space-settings/spec.md` (this file's sibling)
- [x] 1.2 Extract `app/Support/UniqueSpaceSlug.php` (static `for(string $name, ?int $ignoreId = null): string`)
- [x] 1.3 Refactor `CreateSpaceAction::create()` to call `UniqueSpaceSlug::for($name)` instead of the inline private method

## 2. UpdateSpaceRequest

- [x] 2.1 Create `app/Http/Requests/UpdateSpaceRequest.php` with rules + messages mirroring `CreateSpaceRequest`
- [x] 2.2 `authorize()` returns `true`; the controller calls `Gate::authorize('update', $space)` after binding

## 3. UpdateSpaceSettingsAction

- [x] 3.1 Create `app/Actions/UpdateSpaceSettingsAction.php` with one public method `update(Space $space, array $validated): Space`
- [x] 3.2 Mutate the six mutable fields; regenerate slug via `UniqueSpaceSlug::for(...)` when `name` changed
- [x] 3.3 Return `$space->fresh()` so the controller can read the new slug

## 4. Controller wiring

- [x] 4.1 Add `updateSettings(Space $space, UpdateSpaceRequest $request, UpdateSpaceSettingsAction $action): RedirectResponse` on `SpaceController`
- [x] 4.2 Add `Gate::authorize('view', $space)` to `dashboard()`, `embed()`, and `settings()` (inbox already had it)
- [x] 4.3 Extend `settings()` to pass the full mutable-field set + `themes` into the Inertia page

## 5. Route registration

- [x] 5.1 Add `Route::patch('/{space}/settings', [SpaceController::class, 'updateSettings'])->name('settings.update');` to `routes/web.php` directly under the existing `settings` GET

## 6. Inertia page (settings.tsx)

- [x] 6.1 Rewrite `resources/js/pages/spaces/settings.tsx`: pre-filled `useForm`, name/title/subtitle/ask inputs, theme Select, rating_enabled Checkbox, read-only public_id display, success flash Alert, destructive Alert on validation errors
- [x] 6.2 Submit posts `patch('/spaces/{slug}/settings')`

## 7. Action test (UpdateSpaceSettingsActionTest)

- [x] 7.1 happy path persists all six fields
- [x] 7.2 name change regenerates slug
- [x] 7.3 slug collision appends `-xxxx`
- [x] 7.4 same name keeps current slug
- [x] 7.5 public_id is unchanged after any save
- [x] 7.6 rating_enabled toggle does not null existing testimonial ratings

## 8. Feature test (SpaceSettingsTest)

- [x] 8.1 GET 200 for owner, page is `spaces/settings` with themes + space
- [x] 8.2 GET 403 for non-owner
- [x] 8.3 PATCH happy path: 302 to `spaces.settings` with success flash
- [x] 8.4 PATCH 422 on invalid payload
- [x] 8.5 PATCH 403 for non-owner
- [x] 8.6 PATCH 404 on soft-deleted Space
- [x] 8.7 PATCH with name change redirects to the new slug

## 9. README/AGENTS (no changes required)

- [x] 9.1 No README.md exists. `AGENTS.md` already documents the action-class + policy patterns; no update needed.

## 10. Quality gates

- [x] 10.1 `vendor/bin/pint --parallel`
- [x] 10.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [x] 10.3 `npx vp check --fix`
- [x] 10.4 `php artisan test` — all green, target ~196 tests (183 + 13 new)

## 11. Commit & push

- [x] 11.1 Single commit on `main`: `Group 17 space settings`
- [x] 11.2 Push to `https://github.com/mehedi2122/testimonial.git`

---

## Verification at Each Group

```bash
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npx vp check --fix
php artisan test
```
