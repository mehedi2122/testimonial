# testimonial-validation Specification

## Purpose

Define the validation rules a submission must satisfy before a `Testimonial` and its `TestimonialValue[]` rows are persisted. These rules cover reserved keys, per-field-type value shapes, and the structural email guarantee per data-model §31.

---

## Requirements

### Requirement: The validation layer SHALL reject `field_key` matching reserved keys (case-insensitive)

The system SHALL enforce this requirement.

Reserved keys are column names on `testimonials` or `spaces`:

- `name`, `email`, `testimonial`, `rating`
- `is_wall_of_love`, `is_hidden`, `is_favorite`
- `public_id`, `slug`, `id`, `user_id`, `space_id`
- `submitted_at`, `created_at`, `updated_at`, `deleted_at`

These names belong to the schema, not to user-defined fields. A custom field with key `name` would collide with the column and corrupt the inbox.

#### Scenario: reserved key rejected

- **WHEN** the submission includes a `values` entry with `field_key = name`
- **THEN** the submission is rejected with `422 { errors: { values: ['field_key name is reserved'] } }`

### Requirement: The validation layer SHALL validate each `TestimonialValue.value` against its `space_fields.type`

The system SHALL enforce this requirement.

- `type = text` → string, max 500 chars
- `type = url` → valid URL per FILTER_VALIDATE_URL, max 2048 chars
- `type = number` → numeric, between -1e9 and 1e9
- `type = image` → string ≤ 255 chars (full size/type cap deferred per data-model §9.3)
- `type = rating` is not permitted on a custom field (rating is already a column on `testimonials`)

#### Scenario: invalid url value

- **WHEN** a value is submitted for a field with `type = url` and the value does not pass `FILTER_VALIDATE_URL`
- **THEN** the submission is rejected with `422 { errors: { 'values.<field_key>': ['must be a valid URL'] } }`

### Requirement: The validation layer SHALL reject `type = email` for any custom field

The system SHALL enforce this requirement.

`testimonial_values` never holds an email address (data-model §3.5, structural). A custom email-typed field would create a leak path that the embed API Resource whitelist would have to remember to block on every render. The cleaner answer is rejection at definition.

#### Scenario: email-typed field rejected

- **WHEN** the Space has a `space_fields` row with `type = email`
- **THEN** any submission to it returns `422 { errors: { 'values.<field_key>': ['email-typed custom fields are not permitted'] } }`

### Requirement: The validation layer SHALL reject submissions where the Space has been soft-deleted (resolved before validation)

The system SHALL enforce this requirement.

This is a controller-level preflight, not a rule, but the validation pipeline never sees a soft-deleted Space.

#### Scenario: soft-deleted Space short-circuits

- **WHEN** the Space matching `public_id` has `deleted_at IS NOT NULL`
- **THEN** the validation layer is never invoked and the response is `404`

### Requirement: The validation layer MAY reject submissions whose `field_key` is not registered on the resolved Space

The system SHALL enforce this requirement.

A custom field key defined for Space A is rejected when submitted to Space B. This is the cross-space field leak guard.

#### Scenario: cross-space field key

- **WHEN** a `values` entry has `field_key = company_name` but Space B has only `social_url` defined
- **THEN** the submission is rejected with `422 { errors: { 'values.<field_key>': ['unknown field_key for this Space'] } }`

---

### Requirement: URL-type values SHALL use the http or https scheme

The validation layer SHALL reject URL values whose scheme is not `http` or `https` (for example `javascript:`, `data:`, `ftp:`), because URL values render as links on public pages.

#### Scenario: javascript URL

- **WHEN** a URL field value is `javascript://x/%0Aalert(1)`
- **THEN** the response is 422
