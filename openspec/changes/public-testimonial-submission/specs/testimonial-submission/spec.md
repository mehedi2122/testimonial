# Capability: Testimonial Submission

## Purpose

Define the contract by which a public (unauthenticated) respondent submits a testimonial to a Space through its embeddable public form. The endpoint accepts Space rows by space_id only.

---

## ADDED Requirements

### Requirement: The endpoint SHALL be a JSON POST at `/s/{public_id}/submissions`

The system SHALL enforce this requirement.

`public_id` is the 12-character immutable identifier on `spaces` (data-model §3.2). It is resolved server-side, never trusted from the client.

#### Scenario: routing

- **WHEN** any HTTP client POSTs to `/s/{public_id}/submissions` with content-type `application/json`
- **THEN** the request is routed to `PublicSubmissionController@store`

### Requirement: The endpoint SHALL be unauthenticated

The system SHALL enforce this requirement.

No session, no API token, no CSRF cookie for an embed-rendered page. CSRF protection is provided by route middleware exempting this path or by using Sanctum's stateless token layer (decided at implementation).

#### Scenario: unauthenticated submission

- **WHEN** an unauthenticated client posts a valid payload
- **THEN** the response is not `401` or `419`

### Requirement: The endpoint SHALL accept a JSON body with these required keys

The system SHALL enforce this requirement.

- `name` — string, 2..120 chars
- `email` — RFC 5324 email
- `testimonial` — string, 10..2000 chars
- `rating` — integer 1..5 if `space.rating_enabled = true`; absent otherwise
- `consent_given` — boolean, must be `true`
- `values` — array of `{ field_key, value }` pairs

#### Scenario: valid payload accepted

- **WHEN** the JSON body satisfies every required key shape
- **THEN** the submission enters the persistence flow

### Requirement: The endpoint SHALL resolve the Space from `public_id` and reject soft-deleted Spaces

The system SHALL enforce this requirement.

A Space with `deleted_at IS NOT NULL` returns `404 { error: 'space_not_found' }`. The check is a query-level `whereNull('deleted_at')`, never a fetch-then-test.

#### Scenario: soft-deleted Space

- **WHEN** a Space with matching `public_id` has `deleted_at IS NOT NULL`
- **THEN** the response is `404 { error: 'space_not_found' }`

### Requirement: When the plan limit is reached the endpoint SHALL return `422 { error: 'limit_reached', plan, limit }`

The system SHALL enforce this requirement.

The check is `Testimonial::where('space_id', $space->id)->whereNull('deleted_at')->count()` compared to `Plan::maxTestimonialsPerSpace()` for the Space owner's plan at request time. A 101st submission is accepted per data-model §5.4 boundary race; the 101st and beyond return this 422.

#### Scenario: at limit

- **WHEN** the count of live testimonials for the Space equals `Plan::maxTestimonialsPerSpace()`
- **THEN** the response is `422 { error: 'limit_reached', plan, limit }`

### Requirement: The endpoint SHALL persist a Testimonial and its TestimonialValue rows in a single DB transaction

The system SHALL enforce this requirement.

On any insert failure the entire submission rolls back. The submission does NOT lock the Space row (rejected per data-model §5.4).

#### Scenario: atomicity on partial failure

- **WHEN** TestimonialValue insertion fails midway through the transaction
- **THEN** no Testimonial row and no TestimonialValue rows are persisted

### Requirement: When `consent_given = false` the endpoint SHALL reject the submission with `422 { error: 'consent_required' }` even if the respondent tries to set `is_wall_of_love = true`

The system SHALL enforce this requirement.

The respondent cannot self-promote. The application-level rule mirrors data-model §15: public visibility requires consent.

#### Scenario: consent denied

- **WHEN** `consent_given = false` in the request
- **THEN** the response is `422 { error: 'consent_required' }`

### Requirement: The endpoint SHALL sanitize `name`, `testimonial`, and every `TestimonialValue.value` on write

The system SHALL enforce this requirement.

`htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')` is applied. Render-side escaping is the renderer's job; this is the write-time belt.

#### Scenario: script tag stripped

- **WHEN** the request contains `<script>` in `testimonial` or any `value`
- **THEN** the persisted text has the tags HTML-encoded

### Requirement: The endpoint SHALL rate-limit to 60 submissions per IP per hour

The system SHALL enforce this requirement.

The rate-limit middleware is a named limiter registered in `bootstrap/app.php` (`public-submissions`). Exceeding the limit returns `429 { error: 'rate_limited', retry_after }`.

#### Scenario: rate limit exceeded

- **WHEN** the same IP makes more than 60 submission attempts in an hour
- **THEN** the response is `429 { error: 'rate_limited', retry_after }`

### Requirement: The endpoint SHALL respond with `201 { ok: true, id, submitted_at }` on success

The system SHALL enforce this requirement.

`id` is the new `Testimonial.id`; `submitted_at` is the UTC timestamp the server recorded. The respondent sees the success state through the Inertia/Vite frontend.

#### Scenario: success response

- **WHEN** the submission is committed
- **THEN** the response is `201 { ok: true, id: <int>, submitted_at: <ISO8601> }`

### Requirement: The endpoint MAY return `503 { error: 'maintenance' }` if `APP_MAINTENANCE_DRIVER = file` indicates maintenance mode

The system SHALL enforce this requirement.

This protects the database during deployments without changing collection logic.

#### Scenario: maintenance mode

- **WHEN** Laravel maintenance mode is enabled
- **THEN** the response is `503 { error: 'maintenance' }`

---

## Out of Scope

- Photo upload handling (deferred per data-model §9.3).
- Email notifications to Space owner on submission.
- Idempotency tokens for retries from flaky connections.
- Per-user rate limits and CAPTCHA.
