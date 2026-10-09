# space-crud — delta

## MODIFIED Requirements

### Requirement: The system SHALL expose three authenticated routes for Space CRUD

The system SHALL enforce this requirement.

- `GET /spaces/create` → renders the create form (Inertia `spaces/create`)
- `POST /spaces` → persists the Space and redirects to `/spaces/{slug}/created`
- `DELETE /spaces/{space}` → soft-deletes the Space

All three routes live behind `auth + verified` middleware. `{space}` is route-bound on the `slug` column (per `Space::getRouteKeyName()`).

#### Scenario: ordering

- **WHEN** the route table is inspected
- **THEN** `/spaces/create` and `POST /spaces` are registered before `/spaces/{space}/*` so the static `/create` segment is not captured by route model binding.

## ADDED Requirements

### Requirement: `GET /spaces/{space}/created` SHALL show the Space's public link

The success page SHALL show the public submission URL (`/s/{public_id}`) with Copy link and View Space actions, plus the Wall of Love URL. Only the owner can open it.

#### Scenario: owner after creating a Space

- **GIVEN** the owner has just created a Space
- **WHEN** they land on `/spaces/{slug}/created`
- **THEN** `public_url` is `/s/{public_id}` and opening it returns the submission form

#### Scenario: non-owner

- **WHEN** another user requests `/spaces/{slug}/created`
- **THEN** the response is `403`

### Requirement: Public links in the app SHALL be built by the server

Pages that show a public link SHALL receive it as a prop built by `Space::publicSubmissionUrl()`, never by concatenating the slug client-side.

#### Scenario: inbox empty state

- **WHEN** the owner opens the Inbox
- **THEN** the `public_url` prop is `/s/{public_id}`
