# testimonial-validation — delta

## REMOVED Requirements

### Requirement: The validation layer SHALL reject submissions where `consent_given = false` and the request sets `is_wall_of_love = true`

**Reason**: `is_wall_of_love` is no longer accepted from submitters at all (see testimonial-submission).
**Migration**: none.

## ADDED Requirements

### Requirement: URL-type values SHALL use the http or https scheme

The validation layer SHALL reject URL values whose scheme is not `http` or `https` (for example `javascript:`, `data:`, `ftp:`), because URL values render as links on public pages.

#### Scenario: javascript URL

- **WHEN** a URL field value is `javascript://x/%0Aalert(1)`
- **THEN** the response is 422
