# public-submission-form — delta

## MODIFIED Requirements

### Requirement: The form SHALL submit a payload matching `SubmitTestimonialRequest` rules

The form SHALL post with `fetch` + `FormData`, because the endpoint is a JSON API, not an Inertia route. The payload keys are `name`, `email`, `testimonial`, `rating` (when chosen), `consent_given` (`1`/`0`, optional checkbox), `values[]` (non-empty answers to non-image fields) and `photos[field_key]` (image fields).

#### Scenario: CSRF token present

- **WHEN** the page renders
- **THEN** `<meta name="csrf-token">` is still present in `<head>`, but the POST does not depend on it: `s/*/submissions` is CSRF-exempt (embeddable endpoint) and protected by the per-IP rate limit and server-side validation instead

#### Scenario: success

- **WHEN** the endpoint answers 201
- **THEN** the page swaps to the "🎉 Boom!" thank-you card

#### Scenario: field errors

- **WHEN** the endpoint answers 422 with `errors`
- **THEN** each message renders under its field (`fields.{key}`, `photos.{key}` or the top-level key)

## ADDED Requirements

### Requirement: The page SHALL apply the Space theme to the whole page

The page SHALL wrap its content in a full-height `space-theme-{theme}` element so the theme, not the visitor's app appearance setting, decides colors.

#### Scenario: modern theme

- **WHEN** the Space theme is `modern`
- **THEN** the page renders with the dark Modern palette
