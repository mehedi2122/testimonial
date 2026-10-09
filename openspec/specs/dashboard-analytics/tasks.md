# Tasks: dashboard-analytics

Ten task groups, each ≤ 2 hours, sequenced so every commit passes `composer ci:check` (npm check + pint + phpstan + phpunit).

Each group ends with a green local check before the next one starts. Commit messages follow `Group 18 <verb> ...`.

This change ships in a single commit on `main` at the end; the groups below are sequencing guidance for the implementer (same pattern `space-settings/tasks.md` used).

---

## 1. Spec & action skeleton

- [x] 1.1 Create `openspec/specs/dashboard-analytics/spec.md` and `tasks.md`
- [x] 1.2 Create `app/Actions/Dashboards/DashboardAnalyticsAction.php` with one public method `for(Space $space, string $range): array`
- [x] 1.3 Range normalization: `7d | 30d | 90d | all`; unknown → `30d`

## 2. Aggregate counts + average rating

- [x] 2.1 `total` = `Space::testimonials()->live()->count()`
- [x] 2.2 `favorites` = same with `is_favorite = true`
- [x] 2.3 `on_wall` = same with `is_wall_of_love = true`
- [x] 2.4 `hidden` = same with `is_hidden = true`
- [x] 2.5 `average_rating` = `avg(rating)` over rows where `rating IS NOT NULL`; `null` when no rated rows or when `space.rating_enabled = false`

## 3. Rating histogram (5 bars)

- [x] 3.1 One `selectRaw('rating, count(*) as c')` grouped by rating 1..5
- [x] 3.2 Returns `[1★, 2★, 3★, 4★, 5★]` count array; missing ratings are zero-filled

## 4. Submissions-per-day bucketing

- [x] 4.1 Window lower bound: `7d → -7 days`, `30d → -30 days`, `90d → -90 days`, `all → earliest submitted_at`
- [x] 4.2 Group by `submitted_at::date` (SQLite) / `DATE(submitted_at)` (MySQL) — small private helper
- [x] 4.3 Zero-fill empty days so the sparkline is contiguous
- [x] 4.4 Return `[ { date: 'YYYY-MM-DD', count: int }, ... ]` ascending

## 5. Plan + limit pass-through

- [x] 5.1 Resolve owner via `$space->user` and call `User::plan()`
- [x] 5.2 `plan_limit = $plan->maxTestimonialsPerSpace()` and `plan = $plan->value`
- [x] 5.3 `live_count` = same as `total` (kept as a separate name for the banner copy)

## 6. Controller wiring

- [x] 6.1 `SpaceController::dashboard(Space $space, Request $request): Response` reads `?range=`
- [x] 6.2 Calls `DashboardAnalyticsAction::for($space, $range)` and merges the payload into the Inertia page
- [x] 6.3 `Gate::authorize('view', $space)` already in place (Group 17)

## 7. Inertia page (dashboard.tsx)

- [x] 7.1 Rewrite `resources/js/pages/spaces/dashboard.tsx` as a real analytics page
- [x] 7.2 KPI strip: 3 Card components (Testimonials, Avg rating, On wall)
- [x] 7.3 Rating histogram: 5 SVG bars; "Ratings disabled" line when `rating_enabled = false`
- [x] 7.4 Submissions-per-day sparkline: pure-SVG bar chart
- [x] 7.5 Range chip row: 4 `<Link>` chips with `?range=` URL handling
- [x] 7.6 Free-plan upgrade banner (informational; no link)
- [x] 7.7 Flash success / error Alerts (matches `inbox.tsx` pattern)

## 8. Action test (DashboardAnalyticsActionTest)

- [x] 8.1 happy path: 10 testimonials returns total = 10, average = 3.6
- [x] 8.2 soft-deleted row excluded from total + sparkline
- [x] 8.3 favorite / wall / hidden counts correct
- [x] 8.4 histogram zero-fills missing ratings
- [x] 8.5 7d / 30d / 90d / all windows return correct bucket counts
- [x] 8.6 invalid range normalizes to 30d
- [x] 8.7 average_rating is null when rating_enabled = false
- [x] 8.8 plan + plan_limit mirror the owner's plan()

## 9. Feature test (DashboardTest)

- [x] 9.1 GET 200 for owner, page is `spaces/dashboard`
- [x] 9.2 GET 403 for non-owner (security-gap lock continues)
- [x] 9.3 default range is 30d
- [x] 9.4 `?range=7d` returns 7 sparkline buckets
- [x] 9.5 invalid `?range=` normalizes to 30d
- [x] 9.6 Free user sees upgrade banner
- [x] 9.7 Pro user does not see upgrade banner

## 10. Quality gates + commit

- [x] 10.1 `vendor/bin/pint --parallel`
- [x] 10.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [x] 10.3 `npx vp check --fix`
- [x] 10.4 `php artisan test` — target ~209 tests (197 + 12 new)
- [x] 10.5 Single commit on `main`: `Group 18 dashboard analytics`
- [x] 10.6 Push to `https://github.com/mehedi2122/testimonial.git`

---

## Verification at Each Group

```bash
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M --no-progress
npx vp check --fix
php artisan test
```
