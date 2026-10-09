# dashboard-analytics Specification

## Purpose

Replace the `/spaces/{slug}/dashboard` placeholder with the real owner-facing analytics page (PRD §6 + §30). Building on the data shipped in Groups 12 (data model) and 16 (testimonial-inbox), the owner can now see total testimonials, average rating, favorite / wall-of-love counts, a 7/30/90-day submissions-per-day chart, and a Free-plan upgrade banner — all calculated from `testimonials` rows with no schema change.

This change is bounded to the owner's own Space. `SpacePolicy::view` (already enforced on the dashboard GET since Group 17) is the single authorization gate.

---

## Requirements

### Requirement: `GET /spaces/{space}/dashboard` SHALL render real analytics instead of the placeholder

The system SHALL enforce this requirement.

The Inertia `analytics` prop is built by `DashboardAnalyticsAction::for($space, $range)` and contains: `total`, `favorites`, `on_wall`, `hidden`, `average_rating` (nullable), `rating_enabled`, `rating_histogram` (array of 5 ints), `submissions_per_day` (array of `{ date, count }`), `range` (echoed back), and `plan` + `plan_limit` + `live_count` (Free-plan upgrade copy). The page renders the existing `SpacePageShell` chrome plus a 3-card KPI strip, the date-filter chips, the histogram, the sparkline, and the upgrade banner.

#### Scenario: dashboard renders for owner

- **WHEN** the owner GETs `/spaces/{slug}/dashboard`
- **THEN** the Inertia page is `spaces/dashboard` and `analytics.total` equals the live testimonial count and `analytics.range` is one of `7d | 30d | 90d | all` (default `30d`).

### Requirement: The action SHALL accept a `range` parameter and bucket submissions per day over that window

The system SHALL enforce this requirement.

`DashboardAnalyticsAction::for(Space $space, string $range)` returns a payload. `range` must be one of `7d`, `30d`, `90d`, `all`; any other value is normalized to `30d`. The action computes `submissions_per_day` over the window by grouping on `submitted_at::date` (SQLite) / `DATE(submitted_at)` (MySQL via `DB::raw`) — the dialect-neutral abstraction is a small `groupByDate` helper inside the action class. Empty days inside the window are filled with zero so the chart is contiguous.

#### Scenario: 7d window

- **WHEN** the owner requests `range = 7d`
- **THEN** `submissions_per_day` has exactly 7 entries, each with a `date` in the last 7 days inclusive, ordered ascending.

#### Scenario: all-time window

- **WHEN** the owner requests `range = all`
- **THEN** `submissions_per_day` covers the full span from the earliest testimonial's `submitted_at` to today, with one entry per day and zero-fills between.

#### Scenario: invalid range

- **WHEN** the owner requests `range = "yesterday"`
- **THEN** the action treats it as `30d` (no error surfaced) and `analytics.range` is `"30d"`.

### Requirement: The action SHALL exclude soft-deleted testimonials from all aggregates

The system SHALL enforce this requirement.

`scopeLive()` (`whereNull('deleted_at')`) is the gate for every count, average, histogram, and sparkline bucket. Soft-deleting a row from the inbox updates the dashboard the next time it loads — without a controller-side change.

#### Scenario: soft-deleted row drops from totals

- **WHEN** the owner soft-deletes a row from `/spaces/{slug}/inbox` and then reloads the dashboard
- **THEN** `analytics.total` decreases by one and the row is not in any histogram or sparkline bucket.

### Requirement: The dashboard SHALL show a 3-card KPI strip with the rating average and a rating histogram when `rating_enabled = true`

The system SHALL enforce this requirement.

KPI cards: **Testimonials** (count), **Avg rating** (one decimal, "—" when `rating_enabled` is false), **On wall of love** (count). A 5-bar histogram shows the distribution of 1★–5★ ratings. When `rating_enabled = false`, the histogram is replaced with a small `muted-foreground` line "Ratings disabled for this Space." — no broken chart.

#### Scenario: ratings enabled

- **WHEN** `space.rating_enabled = true` and there are 10 testimonials with ratings [5, 5, 4, 4, 4, 3, 3, 2, 1, 5]
- **THEN** `analytics.average_rating = 3.6`, the 5-bar histogram reads `[1, 1, 2, 3, 3]`, and the KPI strip renders all three cards.

#### Scenario: ratings disabled

- **WHEN** `space.rating_enabled = false`
- **THEN** `analytics.average_rating = null` and the histogram block shows the "Ratings disabled" line.

### Requirement: The page SHALL render a date-range filter with chips

The system SHALL enforce this requirement.

Four chips: **7d**, **30d**, **90d**, **All time**. The active chip is highlighted (default `30d`); clicking a chip navigates to the dashboard with `?range={value}` and the page re-renders. Chips are `<Link>` tags, not `<button>`s — the URL is the source of truth and back/forward navigation works.

#### Scenario: chip click

- **WHEN** the owner clicks the `7d` chip while on `?range=30d`
- **THEN** the browser navigates to `/spaces/{slug}/dashboard?range=7d` and the dashboard re-renders with 7 sparkline bars and a `range` highlight on the `7d` chip.

### Requirement: The page SHALL render a Free-plan upgrade banner when applicable

The system SHALL enforce this requirement.

The banner reads: **"You're on the Free plan — X of Y testimonials collected. Upgrade to Pro to collect up to 1,000 per Space."** It is shown only when `plan = 'free'`. The Pro plan and any case where `live_count < plan_limit` hide the banner. The banner does not link anywhere in this change (the billing page is a separate OpenSpec change); it is informational only.

#### Scenario: Free user below limit

- **WHEN** a Free user with 27 of 100 testimonials loads the dashboard
- **THEN** the banner reads "You're on the Free plan — 27 of 100 testimonials collected. Upgrade to Pro to collect up to 1,000 per Space."

#### Scenario: Pro user

- **WHEN** a Pro user loads the dashboard
- **THEN** the banner is not rendered.

#### Scenario: Free user at limit

- **WHEN** a Free user with 100 of 100 testimonials loads the dashboard
- **THEN** the banner is rendered with the same copy (the limit is the same; the in-app form already blocks the 101st submission with the "limit_reached" envelope from Group 11).

### Requirement: The action SHALL be a one-method class under `app/Actions/Dashboards/`

The system SHALL enforce this requirement.

`App\Actions\Dashboards\DashboardAnalyticsAction::for(Space $space, string $range): array` is the only writer. Controllers do not query testimonials directly. The action lives under a `Dashboards` sub-namespace to mirror `app/Actions/Inbox/` (Group 16) and `app/Actions/Public/` (Group 10).

#### Scenario: action location

- **WHEN** the file tree is inspected
- **THEN** `app/Actions/Dashboards/DashboardAnalyticsAction.php` exists and has exactly one public method.

### Requirement: The page SHALL use pure-SVG bars for the histogram and sparkline (no new JS dependency)

The system SHALL enforce this requirement.

The PRD §2 lists "Recharts or an equivalent React charting library." The project has no charting library installed today. For the 5-bar histogram and the 30-bar sparkline, hand-rolled SVG is the equivalent and avoids adding ~100KB to the bundle. If a richer chart (tooltips, multi-series) is later needed, a charting dep can be introduced in a dedicated `dashboard-charts` change.

#### Scenario: no new npm dependency

- **WHEN** `package.json` is inspected after this change
- **THEN** the `dependencies` block is unchanged.

---

## Out of scope (explicit)

- Recharts (or any charting library) — see Requirement above. Pure-SVG is the MVP.
- Cross-Space aggregate dashboard at `/spaces` (the index page is for Space management, not analytics). A future `global-analytics` change could add it.
- CSV / PDF export of analytics.
- Real-time updates (websockets, polling, or Livewire). The dashboard is server-rendered per request; `?range=` changes require a full GET.
- Conversion funnel ("how many visitors submitted"), since there's no visitor-side event collection in MVP.
- Date-range beyond 90 days as a chip — present-day data caps at 90 days for chart density.
- Click-throughs to the inbox from a sparkline bucket (separate UX call).
- A "Plan limits" widget showing per-`Plan` cap — the in-page banner already surfaces the per-Space limit.
