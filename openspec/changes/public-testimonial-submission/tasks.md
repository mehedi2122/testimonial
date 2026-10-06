# Tasks: Public Testimonial Submission Endpoint

Twelve task groups, each ≤ 2 hours, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

Each group ends with a green local `composer ci:check` before the next one starts. Commit messages follow `Add OpenSpec — <verb> ...`.

---

## 1. Route + Controller skeleton

- [x] 1.1 Add `POST /s/{public_id}/submissions` returning a placeholder `201` with the resolved Space's name
- [x] 1.2 Wire rate-limit middleware binding in `bootstrap/app.php` (named limiter `public-submissions:60/hour`)
- [x] 1.3 Pest test `it routes /s/{public_id}/submissions to PublicSubmissionController@store`

## 2. FormRequest with base validation

`SubmitTestimonialRequest` rules:

- [x] 2.1 `name` required string 2..120
- [x] 2.2 `email` required email:rfc 5324
- [x] 2.3 `testimonial` required string 10..2000
- [x] 2.4 `rating` required integer min:1 max:5 (only if `rating_enabled`)
- [x] 2.5 `consent_given` required boolean (must be true to accept — PRD §13 social-sharing consent)
- [x] 2.6 `values` array; each entry `field_key` + `value`
- [x] 2.7 Feature test rejects each rule by return count, accepts a minimal valid payload

## 3. Custom rule: ReservedFieldKey

- [x] 3.1 `ReservedFieldKey` rejects `field_key` matching `^(name|email|testimonial|rating|consent_given|is_wall_of_love|is_hidden|is_favorite|public_id|slug)$` (case-insensitive)
- [x] 3.2 Unit test: 10+ reserved keys rejected, 5+ allowed keys accepted

## 4. Custom rule: SubmissionValue (per space_fields.type)

- [x] 4.1 `SubmissionValue` validates `value` against `space_fields.type`
- [x] 4.2 `type = text` → string, max 500 chars
- [x] 4.3 `type = url` → valid URL, max 2048 chars
- [x] 4.4 `type = number` → numeric, between -1e9 and 1e9
- [x] 4.5 `type = email` → never (rejected; structural §31)
- [x] 4.6 `type = rating` → never for custom fields
- [x] 4.7 Unit test covers each type and the rejection paths

## 5. Custom rule: FieldTypeSpaceFieldVisibility

> **Superseded by Group 4.** `SubmissionValue`'s `SpaceFieldType::Image` arm already enforces
> `string ≤ 255 chars` (the same shape the original Group 5 spec described). A second rule would be
> an unused duplicate. Marked done via Group 4. Full photo upload/size rules remain deferred per
> data-model §9.3 and will be added to `SubmissionValue::validateImage` when decided.

- [x] 5.1 Image value shape (string ≤ 255) — covered by `SubmissionValue` `SpaceFieldType::Image`
- [x] 5.2 Image unit test — covered by `tests/Unit/Rules/SubmissionValueTest.php`

## 6. Space resolution and SoftDeletes preflight

- [ ] 6.1 Controller: load Space by `public_id` with `->whereNull('deleted_at')`
- [ ] 6.2 If not found, return `404 { error: 'space_not_found' }`
- [ ] 6.3 Feature test: deleted Space yields 404; live Space yields 200

## 7. Plan-limit check (§4.3, §5.4)

- [ ] 7.1 `SubmitTestimonialAction` count-check `$liveCount = Testimonial::where('space_id', $space->id)->whereNull('deleted_at')->count();`
- [ ] 7.2 Compare against `Plan::maxTestimonialsPerSpace()` for the Space owner's `plan()`
- [ ] 7.3 Reject with `422 { error: 'limit_reached', plan, limit }` if at or above
- [ ] 7.4 Feature test: 100/100 returns 422; 99/100 with Pro plan accepts

## 8. Create Testimonial + TestimonialValue[] atomically

- [ ] 8.1 DB transaction wraps: Testimonial create + TestimonialValue foreach
- [ ] 8.2 Use `connection()->transaction()` (no Space-row lock; rejected per §5.4)
- [ ] 8.3 Feature test: rolls back on a mid-transaction failure; commits on success

## 9. is_wall_of_love preflight (§15)

- [ ] 9.1 Reject `is_wall_of_love = true` while `consent_given = false`
- [ ] 9.2 Return `422 { error: 'consent_required' }`
- [ ] 9.3 Feature test: consent=false with wall_of_love=true rejected

## 10. Sanitization on write (§6)

- [ ] 10.1 Apply `htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')` to `name`, `testimonial`, and each `TestimonialValue->value`
- [ ] 10.2 Unit test: script tags stripped from name and from each value

## 11. Inertia/Vite wiring

- [ ] 11.1 Locate the existing submit form component (search resources/components or resources/js)
- [ ] 11.2 Replace mock `submit()` with `fetch('/s/{public_id}/submissions', { method: 'POST', body: JSON })`
- [ ] 11.3 Surface `{ ok: true }`, `{ error: 'rate_limited' }`, `{ error: 'limit_reached' }`, and Inertia error bag
- [ ] 11.4 Verify: existing form tests pass; add happy-path browser-less test if the setup supports it; otherwise document as a manual test

## 12. README + OpenSpec archive

- [ ] 12.1 Update `README.md` with one paragraph describing the public endpoint and its URL pattern
- [ ] 12.2 Run `npx openspec validate public-testimonial-submission --strict` to confirm structure
- [ ] 12.3 Run `npx openspec archive public-testimonial-submission` to move specs under `openspec/specs/` and mark complete

---

## Verification at Each Group

After every group, before the next commit:

```bash
npm run check
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M
php artisan test
```

All four must pass. The GitHub Actions `tests.yml` workflow runs the same chain on push and is the final acceptance gate.
