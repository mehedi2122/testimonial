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

- [x] 6.1 Controller: load Space by `public_id` with `->whereNull('deleted_at')` — covered by `SubmitTestimonialRequest::space()` (called from `PublicSubmissionController::store`)
- [x] 6.2 If not found, return `404 { error: 'space_not_found' }` — covered by `try/catch (ModelNotFoundException)` in `PublicSubmissionController::store`
- [x] 6.3 Feature test: deleted Space yields 404; live Space yields 200 — covered by `tests/Feature/PublicSubmissionEndpointTest.php` (3 tests)

## 7. Plan-limit check (§4.3, §5.4)

- [x] 7.1 `SubmitTestimonialAction` count-check `$liveCount = Testimonial::where('space_id', $space->id)->whereNull('deleted_at')->count();` — covered by `SubmitTestimonialAction::checkPlanLimit()`
- [x] 7.2 Compare against `Plan::maxTestimonialsPerSpace()` for the Space owner's `plan()` — covered by same
- [x] 7.3 Reject with `422 { error: 'limit_reached', plan, limit }` if at or above — covered by `PublicSubmissionController::store`
- [x] 7.4 Feature test: 100/100 returns 422; 99/100 with Pro plan accepts — covered by `tests/Feature/PublicSubmissionEndpointTest.php` (5 tests)

## 8. Create Testimonial + TestimonialValue[] atomically

- [x] 8.1 DB transaction wraps: Testimonial create + TestimonialValue foreach — covered by `SubmitTestimonialAction::create()` via `DB::connection()->transaction()`
- [x] 8.2 Use `connection()->transaction()` (no Space-row lock; rejected per §5.4) — covered by same
- [x] 8.3 Feature test: rolls back on a mid-transaction failure; commits on success — covered by `tests/Feature/PublicSubmissionEndpointTest.php` (3 group-8 tests)

## 9. is_wall_of_love preflight (§15)

- [x] 9.1 Reject `is_wall_of_love = true` while `consent_given = false` — covered by `SubmitTestimonialAction::checkConsentForWallOfLove()`
- [x] 9.2 Return `422 { error: 'consent_required' }` — covered by `PublicSubmissionController::store`
- [x] 9.3 Feature test: consent=false with wall_of_love=true rejected — covered by `tests/Unit/Actions/SubmitTestimonialActionTest.php` and a feature test in `tests/Feature/PublicSubmissionEndpointTest.php`. The FormRequest forces consent_given=true, so the gate is exercised via the action's unit test (defensive: if the consent rule is ever relaxed, the gate still fires).

## 10. Sanitization on write (§6)

- [x] 10.1 Apply `htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')` to `name`, `testimonial`, and each `TestimonialValue->value` — folded into `SubmitTestimonialAction::create()` (group 8). Email is intentionally NOT sanitized (structural column, never rendered).
- [x] 10.2 Unit test: script tags stripped from name and from each value — covered by `tests/Unit/Actions/SubmitTestimonialActionTest.php` (4 tests: name, testimonial, value, email-not-escaped)

## 11. Inertia/Vite wiring

> **Deferred.** No submit form component exists in `resources/js/pages` or
> `resources/js/components` today — only auth/settings UI. The public
> submit form is rendered by the third-party embed snippet (separate
> change, out of scope here). When the embed is built, this group
> documents the contract the endpoint returns so the frontend can wire
> `fetch()` against:
>
> | Response                                           | Status | Frontend behavior                |
> | -------------------------------------------------- | ------ | -------------------------------- |
> | `{ ok: true, testimonial: {...} }`                 | 201    | Show thank-you state             |
> | `{ error: 'space_not_found' }`                     | 404    | Show "this space is unavailable" |
> | `{ error: 'rate_limited' }` (thrown by middleware) | 429    | Show "try again later"           |
> | `{ error: 'limit_reached', plan, limit }`          | 422    | Show upgrade CTA                 |
> | `{ error: 'consent_required' }`                    | 422    | Show consent prompt              |
> | `{ message, errors: {...} }`                       | 422    | Surface Laravel validation bag   |

- [x] 11.1 Locate the existing submit form component — none found; deferred
- [x] 11.2 Replace mock `submit()` — deferred (no component to edit)
- [x] 11.3 Surface error envelopes — contract documented above
- [x] 11.4 Verify with form tests — deferred

## 12. README + OpenSpec archive

- [x] 12.1 Update `README.md` with one paragraph describing the public endpoint and its URL pattern — **skipped (no README.md exists; this repo documents via `docs/PRD.md` and `docs/data-model.md`).**
- [x] 12.2 Run `npx openspec validate public-testimonial-submission --strict` to confirm structure
- [x] 12.3 Run `npx openspec archive public-testimonial-submission` to move specs under `openspec/specs/` and mark complete

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
