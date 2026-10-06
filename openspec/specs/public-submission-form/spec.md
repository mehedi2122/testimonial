# public-submission-form Specification

## Purpose

Define the respondent-facing page at `/s/{public_id}` so a Space's testimonial collection form is reachable from the embed snippet, the share link, and the public wall. The submission API (`POST /s/{public_id}/submissions`) is already shipped under `public-testimonial-submission`; this change adds only the GET page that renders it.

---

## Requirements

### Requirement: The system SHALL expose a public `GET /s/{public_id}` route

The system SHALL enforce this requirement.

The route is named `public.submissions.show`. It is **not** throttled (the GET is read-only and renders only the form — submissions themselves go through the throttled POST endpoint). No authentication is required.

#### Scenario: routing

- **WHEN** any HTTP client GETs `/s/{public_id}`
- **THEN** the request is routed to `PublicSubmissionController@show` and returns an Inertia `public/submit` page

### Requirement: The page SHALL resolve the Space by `public_id` and reject soft-deleted Spaces

The system SHALL enforce this requirement.

`Space::getRouteKeyName()` returns `slug`, so route model binding cannot be used. The controller resolves the Space via an explicit query: `Space::where('public_id', $publicId)->whereNull('deleted_at')->firstOrFail()`. A soft-deleted or unknown id produces a 404.

#### Scenario: soft-deleted Space

- **WHEN** the resolved Space has `deleted_at IS NOT NULL`
- **THEN** the response is 404

### Requirement: The page SHALL render only `SpaceField` rows where `mode != Off`

The system SHALL enforce this requirement.

The fields prop on the page is filtered to exclude `SpaceFieldMode::Off` and sorted by `sort_order`. Each rendered row carries `{ field_key, label, type, mode, required }`.

#### Scenario: field filtering

- **WHEN** a Space has `company_name` at `optional` and `social_url` + `profile_photo` at `off`
- **THEN** the form renders only `company_name`

### Requirement: The page SHALL render rating input only when `space.rating_enabled = true`

The system SHALL enforce this requirement.

The page reads `space.rating_enabled` from props. When true, a 5-star radio group is rendered. When false, the rating block is omitted entirely (the form payload omits `rating`).

#### Scenario: rating conditional

- **WHEN** `space.rating_enabled = true`
- **THEN** the page renders a 5-star input; selecting a star submits `rating = 1..5`; clearing resets to null.

### Requirement: The form SHALL submit a payload matching `SubmitTestimonialRequest` rules

The system SHALL enforce this requirement.

The payload keys are `name`, `email`, `testimonial`, `rating` (optional), `consent_given = true` (always), and `values[]` (one entry per non-off field, each `{ field_key, value }`).

#### Scenario: CSRF token present

- **WHEN** the page renders
- **THEN** `<meta name="csrf-token">` is present in `<head>` so `@inertiajs/react`'s `useForm` can include it as `X-CSRF-TOKEN` and the POST is not rejected with 419.

### Requirement: On 201 the page SHALL swap to a thank-you card in place

The system SHALL enforce this requirement.

The success card reads the response (`{ ok: true, testimonial: { id, submitted_at } }`) and replaces the form. The URL does not change. Reloading the page returns to a fresh form (success state is not persisted).

#### Scenario: success card

- **WHEN** the POST returns `201 { ok: true, testimonial: { id, submitted_at } }`
- **THEN** the form is hidden and a thank-you card with "🎉 Boom! Your testimonial has been submitted" copy is rendered.

### Requirement: On 422 the page SHALL render validation errors and plan-limit messages distinctly

The system SHALL enforce this requirement.

- Validation errors (per-field): rendered inline below each field via `useForm().errors`, mirroring the existing `spaces/create.tsx` pattern.
- Plan-limit (`{ error: 'limit_reached', plan, limit }`): rendered as a destructive `Alert` at the top of the form, separate from per-field errors. The form remains visible so the respondent can review.

#### Scenario: plan-limit copy

- **WHEN** the POST returns `422 { error: 'limit_reached', plan: 'free', limit: 100 }`
- **THEN** a destructive Alert reads "This Space has reached its free plan limit of 100 testimonials."

### Requirement: The page SHALL NOT use `AppLayout` or `AuthLayout`

The system SHALL enforce this requirement.

Public submission lives outside the authenticated app shell. `resources/js/app.tsx` routes `public/*` through the same null-layout path as `welcome`. The page renders its own section container with the Space's theme token prepended.

#### Scenario: layout selection

- **WHEN** the page component renders
- **THEN** the surrounding Inertia layout is null (no sidebar, no auth card).
