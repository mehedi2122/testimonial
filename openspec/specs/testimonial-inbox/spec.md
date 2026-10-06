# testimonial-inbox Specification

## Purpose

Turn the owner-facing `/spaces/{slug}/inbox` page from a placeholder into a real moderation queue. Building on `public-testimonial-submission` (the POST endpoint) and `public-submission-form` (the public form page), owners can now list every testimonial for a Space, toggle favorite / wall-of-love / hidden flags, edit name/body/rating, and soft-delete — all behind a centralized `SpacePolicy` / `TestimonialPolicy`.

This change also extracts the two policies now that mutation surfaces cross the 5-method threshold — the inline `abort_unless(...)` checks from `space-crud` are gone.

---

## Requirements

### Requirement: The page SHALL list live testimonials ordered favorites-first then newest-first

The system SHALL enforce this requirement.

The Inertia `testimonials` prop is built by `Space::testimonials()->live()->with(['values.spaceField'])->orderByDesc('is_favorite')->orderByDesc('submitted_at')`. Soft-deleted rows are excluded by the `live` scope.

#### Scenario: ordering

- **WHEN** the inbox loads
- **THEN** rows where `is_favorite = true` appear before `is_favorite = false`; ties on the favorite flag are broken by `submitted_at DESC`.

### Requirement: The page SHALL render a plan-limit indicator

The system SHALL enforce this requirement.

The Inertia page receives `{ live_count, plan_limit, plan }` from the controller. The header shows "{live_count} of {plan_limit} collected" plus the plan label (e.g. `(free plan)`).

#### Scenario: indicator copy

- **WHEN** the owner is on the Free plan and has 27 testimonials
- **THEN** the header reads "27 of 100 collected (free plan)".

### Requirement: The page SHALL render an empty state when no testimonials exist

The system SHALL enforce this requirement.

When `testimonials` is empty, the page renders a centered message that includes a link to `/s/{public_id}` so the owner can preview the public submission form.

#### Scenario: empty state

- **WHEN** a Space has zero live testimonials
- **THEN** a card reads "No testimonials yet" and a link to `/s/{public_id}` is rendered.

### Requirement: The page SHALL render action buttons per row

The system SHALL enforce this requirement.

Each row carries five actions: favorite toggle, wall-of-love toggle, hidden toggle, edit, delete. Toggle actions `POST` to dedicated routes; edit opens a `Dialog`; delete opens a confirmation `Dialog`.

#### Scenario: row actions

- **WHEN** a row is displayed
- **THEN** Star / Wall / Hide / Edit / Delete buttons are rendered with the correct `aria-pressed` reflecting the current flag state.

### Requirement: Each toggle SHALL post to a dedicated route

The system SHALL enforce this requirement.

Routes:

| Action              | Method                                                  | Route name                  |
| ------------------- | ------------------------------------------------------- | --------------------------- |
| Toggle favorite     | `POST /spaces/{space}/inbox/{testimonial}/favorite`     | `spaces.inbox.favorite`     |
| Toggle wall-of-love | `POST /spaces/{space}/inbox/{testimonial}/wall-of-love` | `spaces.inbox.wall-of-love` |
| Toggle hidden       | `POST /spaces/{space}/inbox/{testimonial}/hidden`       | `spaces.inbox.hidden`       |
| Edit                | `PATCH /spaces/{space}/inbox/{testimonial}`             | `spaces.inbox.update`       |
| Delete              | `DELETE /spaces/{space}/inbox/{testimonial}`            | `spaces.inbox.destroy`      |

#### Scenario: favorite round-trip

- **WHEN** the owner clicks Star on a row
- **THEN** `POST /spaces/{slug}/inbox/{id}/favorite` returns 302 with `flash.success = "Marked as favorite."`, and the row's `is_favorite` becomes `true`.

### Requirement: The endpoints SHALL be authorized by `SpacePolicy` and `TestimonialPolicy`

The system SHALL enforce this requirement.

`SpacePolicy::view` gates the inbox GET; `TestimonialPolicy::moderate` gates the three flag POSTs; `TestimonialPolicy::update` gates PATCH; `TestimonialPolicy::delete` gates DELETE. Each check follows the rule `$user->id === $space->user_id`. Cross-tenant row IDs return 404 (not 403) via the controller's PK-scoping check.

#### Scenario: non-owner moderation

- **WHEN** a user other than the Space owner POSTs to `inbox.favorite`
- **THEN** the response is 403 and the row is unchanged.

#### Scenario: cross-tenant id

- **WHEN** a user POSTs to `/spaces/{their-space-slug}/inbox/{other-space-testimonial-id}/favorite`
- **THEN** the response is 404 (not 403) and the row is unchanged.

### Requirement: `is_public` SHALL be recomputed on every save

The system SHALL enforce this requirement.

The existing `Testimonial::saving` listener recomputes the column on SQLite (and the STORED generated column does the same on MySQL). Toggling `is_wall_of_love` or `is_hidden` re-evaluates `is_public` automatically; no caller-side refresh is required. `scopePubliclyVisible()` re-evaluates the rule at read time and remains the source of truth for the public wall.

#### Scenario: hide a public testimonial

- **WHEN** the owner toggles `is_hidden = true` on a row that was `is_public = true`
- **THEN** after the save the row's `is_public` is `false` and the testimonial no longer appears in `scopePubliclyVisible()`.

### Requirement: Update SHALL sanitize free-text fields

The system SHALL enforce this requirement.

`UpdateTestimonialAction` uses the `SanitizesFreeText` trait (same rule as `SubmitTestimonialAction`). `name` and `testimonial` go through the encoder before write so the storage shape is identical regardless of who wrote the row.

#### Scenario: edit with script tags

- **WHEN** the owner saves `name = "<script>alert(1)</script>Alice"`
- **THEN** the stored row's name contains `&lt;script&gt;` and never the raw `<script` substring.

### Requirement: Update SHALL NOT accept an email

The system SHALL enforce this requirement.

`UpdateTestimonialRequest::rules()` does not declare `email`. Even if a payload includes an email, it is ignored — the row's email stays locked to the original submission. This matches the §15 data-model guarantee (`testimonial_values` never holds an email).

#### Scenario: edit does not change email

- **WHEN** the owner PATCHes `name`, `testimonial`, `rating`
- **THEN** the row's `email` is unchanged before and after.

### Requirement: Delete SHALL soft-delete the row

The system SHALL enforce this requirement.

`DeleteTestimonialAction` calls `SoftDeletes::delete()`. The row stays archived (visible via `withTrashed()`) and is removed from the inbox query. The submission's email and field values are not lost.

#### Scenario: inbox query after delete

- **WHEN** the owner deletes a row
- **THEN** `GET /spaces/{slug}/inbox` shows `live_count - 1` testimonials, and the deleted row is not in the list.

---

## Out of scope (explicit)

- Bulk actions (multi-select + bulk favorite) — separate change
- Filtering the visible row set by rating / hidden / wall-of-love — separate change
- Pagination — inbox loads all (limit 100 / 1000 per Space; OK for MVP)
- Reply-to-respondent email — separate change
- Public wall-of-love rendering (`public-wall-of-love`) — separate change
- Per-field owner-side moderation toggles — separate change
