# Public Testimonial Submission Endpoint

## Why

Phase 1 shipped a complete `spaces`, `space_fields`, `testimonials`, `testimonial_values`, `embed_configurations` data model with 57 passing tests and a green CI build. The model is in place but no customer can submit a testimonial yet — there is no public endpoint, no validation rules, and no place for the embed JS to POST to. Without this change the SaaS cannot collect a single row of data through its own product surface.

## What Changes

- Add `POST /s/{public_id}/submissions` (JSON) accepting a respondent's submission for a Space resolved by `public_id`. The route is public (no auth), rate-limited, and gated by Space `deleted_at IS NULL`.
- Implement `SubmitTestimonialAction` orchestrating: field validation → custom-field validation per `space_fields.type` → §15 visibility preflight → plan-limit check (`Testimonial::where(...)->count() < $plan->maxTestimonialsPerSpace()`) → atomic create of `Testimonial` + `TestimonialValue[]` in one DB transaction.
- Implement three custom validation rules under `app/Rules/`: `ReservedFieldKey` (rejects reserved keys per §31), `FieldTypeSpaceFieldVisibility` (rejects attachments for `type = 'image'` that violate size/type caps deferred to §9.3), and `SubmissionValue` (per-field-type validators: url/numeric/text length/range).
- Wire route-level rate limiting (60 submissions / IP / hour) and a request-level honeypot-style spam check (deferred §9.5, out of scope here).
- Add Inertia/Vite JS-side wiring for the existing public form component to POST to the new endpoint (frontend glue; no new components).

## Out of Scope

- Email notifications to Space owner on submission (deferred).
- Photo upload handling (deferred per §9.3 — type/size cap decided later).
- Authenticated submission API (admin-side bulk upload; not in PRD §13).
- Idempotency tokens for retries from flaky connections (deferred).
- Per-user rate limits and CAPTCHA (deferred).
- The embed JS file itself (separate change: `embed-js-renderer`).

## Spec Files

- `specs/testimonial-submission/spec.md` — endpoint contract, request/response shape, error model
- `specs/testimonial-validation/spec.md` — validation rules per `space_fields.type`, reserved keys, allowed value shapes

## Tasks File

See `tasks.md` — twelve groups, each ≤ 2 hours, sequenced for safe CI at every commit.

## Affected Code

- New: `app/Actions/SubmitTestimonialAction.php`, `app/Http/Controllers/PublicSubmissionController.php`, `app/Rules/{ReservedFieldKey,FieldTypeSpaceFieldVisibility,SubmissionValue}.php`, `app/Http/Requests/SubmitTestimonialRequest.php`, `routes/web.php` (one new route)
- New tests: `tests/Feature/SubmitTestimonialEndpointTest.php`, `tests/Unit/Rules/*Test.php`
- Modified: `bootstrap/app.php` (rate-limit middleware binding), `resources/js/submit-form.js` (or equivalent — discover on implementation)
