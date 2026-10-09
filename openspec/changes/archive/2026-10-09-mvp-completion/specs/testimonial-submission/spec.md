# testimonial-submission — delta

## MODIFIED Requirements

### Requirement: The endpoint SHALL accept a JSON body with these required keys

The system SHALL enforce this requirement. The body may be JSON or multipart (multipart when a photo is attached).

- `name` — string, 2..120 chars
- `email` — RFC 5324 email
- `testimonial` — string, 10..2000 chars
- `rating` — integer 1..5 if `space.rating_enabled = true`; absent otherwise
- `consent_given` — optional boolean (PRD §13); absent means `false`
- `values` — array of `{ field_key, value }` pairs for non-image fields
- `photos` — optional map of image `field_key` => JPG/PNG/WebP file, at most 2 MB

#### Scenario: valid payload accepted

- **WHEN** the body satisfies every required key shape
- **THEN** the submission enters the persistence flow

#### Scenario: consent omitted

- **WHEN** `consent_given` is false or absent
- **THEN** the testimonial is stored with `consent_given = false` and can never be public

### Requirement: The endpoint SHALL sanitize `name`, `testimonial`, and every `TestimonialValue.value` on write

The system SHALL strip HTML tags and control characters and store plain text. Text SHALL NOT be entity-encoded on write; renderers escape on output.

#### Scenario: script tag stripped

- **WHEN** the request contains `<script>` in `testimonial` or any `value`
- **THEN** the persisted text has the tags removed and `&` is stored as `&`

## REMOVED Requirements

### Requirement: When `consent_given = false` the endpoint SHALL reject the submission with `422 { error: 'consent_required' }` even if the respondent tries to set `is_wall_of_love = true`

**Reason**: PRD §13 makes consent optional, and submitters can no longer set `is_wall_of_love` at all.
**Migration**: none; the owner publishes from the inbox, which refuses Wall of Love without consent.

## ADDED Requirements

### Requirement: The endpoint SHALL never let a submitter publish

The endpoint SHALL ignore any `is_wall_of_love` in the request and store `false`; only the owner publishes (PRD §17).

#### Scenario: self-publish attempt

- **WHEN** a submission includes `is_wall_of_love = true` and `consent_given = true`
- **THEN** the stored testimonial has `is_wall_of_love = false` and is not publicly visible

### Requirement: The endpoint SHALL enforce the Space's field configuration

Required fields SHALL have a non-empty value (or an uploaded photo for image fields). Answers for `mode = off` fields, repeated `field_key`s, and text values for image fields SHALL be rejected with 422 under `fields.{field_key}`.

#### Scenario: required field missing

- **WHEN** a required field has no value
- **THEN** the response is 422 with an error on `fields.{field_key}`

#### Scenario: answer for a disabled field

- **WHEN** a value targets a field whose mode is `off`
- **THEN** the response is 422 with an error on `fields.{field_key}`

### Requirement: Unknown or deleted Spaces SHALL 404 before field validation

The endpoint SHALL respond `404 { error: 'space_not_found' }` before validating fields.

#### Scenario: unknown public_id with values

- **WHEN** the public_id does not resolve and the body has `values`
- **THEN** the response is 404, not 422
