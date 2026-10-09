<?php

declare(strict_types=1);

namespace App\Actions\Dashboards;

use App\Models\Space;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Owner-facing dashboard analytics (OpenSpec change: dashboard-analytics).
 *
 * Single Responsibility: the read-side aggregation for
 * `/spaces/{slug}/dashboard`. The controller stays thin (auth + dispatch);
 * this class owns the SQL — counts, averages, histogram, bucketed
 * submissions-per-day — and the range normalization.
 *
 * All aggregates honor `scopeLive()` (soft-deleted rows are excluded),
 * so deleting a testimonial from the inbox updates the dashboard the
 * next time it loads without a controller-side change.
 *
 * The PRD §2 lists "Recharts or an equivalent React charting library";
 * the histogram and sparkline are pure SVG on the React side and this
 * action ships the raw counts — no server-side rendering.
 */
class DashboardAnalyticsAction
{
    public const RANGE_7D = '7d';

    public const RANGE_30D = '30d';

    public const RANGE_90D = '90d';

    public const RANGE_ALL = 'all';

    /**
     * Build the dashboard payload for `$space` over `$range`.
     * Unknown ranges normalize to {@see self::RANGE_30D}.
     *
     * @return array{
     *     range: string,
     *     total: int,
     *     favorites: int,
     *     on_wall: int,
     *     hidden: int,
     *     average_rating: float|null,
     *     rating_enabled: bool,
     *     rating_histogram: array{0: int, 1: int, 2: int, 3: int, 4: int},
     *     submissions_per_day: array<int, array{date: string, count: int}>,
     *     plan: string,
     *     plan_limit: int,
     *     live_count: int,
     * }
     */
    public function for(Space $space, string $range): array
    {
        $range = $this->normalizeRange($range);

        $live = $space->testimonials()->live();
        $total = (clone $live)->count();
        $favorites = (clone $live)->where('is_favorite', true)->count();
        $onWall = (clone $live)->where('is_wall_of_love', true)->count();
        $hidden = (clone $live)->where('is_hidden', true)->count();

        $averageRating = $space->rating_enabled
            ? (clone $live)->whereNotNull('rating')->avg('rating')
            : null;
        $averageRating = $averageRating === null ? null : (float) $averageRating;

        $histogram = $space->rating_enabled
            ? $this->ratingHistogram($space)
            : [0, 0, 0, 0, 0];

        $submissions = $this->submissionsPerDay($space, $range);

        $owner = $space->user;
        $plan = $owner->plan();
        $planLimit = $plan->maxTestimonialsPerSpace();

        return [
            'range' => $range,
            'total' => $total,
            'favorites' => $favorites,
            'on_wall' => $onWall,
            'hidden' => $hidden,
            'average_rating' => $averageRating,
            'rating_enabled' => $space->rating_enabled,
            'rating_histogram' => $histogram,
            'submissions_per_day' => $submissions,
            'plan' => $plan->value,
            'plan_limit' => $planLimit,
            'live_count' => $total,
        ];
    }

    /**
     * Normalize the request's `range` query parameter to one of the
     * four supported values. Unknown input collapses to 30d.
     */
    private function normalizeRange(string $range): string
    {
        return match ($range) {
            self::RANGE_7D, self::RANGE_30D, self::RANGE_90D, self::RANGE_ALL => $range,
            default => self::RANGE_30D,
        };
    }

    /**
     * Window lower bound for `$range`. `all` is computed from the
     * earliest live testimonial's `submitted_at`.
     *
     * @return array{0: Carbon, 1: Carbon} [from, to] inclusive on both ends
     */
    private function windowBounds(Space $space, string $range): array
    {
        $to = Carbon::now()->endOfDay();
        $from = match ($range) {
            self::RANGE_7D => Carbon::now()->subDays(6)->startOfDay(),
            self::RANGE_30D => Carbon::now()->subDays(29)->startOfDay(),
            self::RANGE_90D => Carbon::now()->subDays(89)->startOfDay(),
            self::RANGE_ALL => $this->allTimeLowerBound($space),
            default => Carbon::now()->subDays(29)->startOfDay(),
        };

        return [$from, $to];
    }

    private function allTimeLowerBound(Space $space): Carbon
    {
        $earliest = $space->testimonials()
            ->live()
            ->min('submitted_at');

        return $earliest
            ? Carbon::parse($earliest)->startOfDay()
            : Carbon::now()->startOfDay();
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int, 4: int}
     *                                                       Counts for ratings 1★..5★, zero-filled.
     */
    private function ratingHistogram(Space $space): array
    {
        $rows = $space->testimonials()
            ->live()
            ->whereNotNull('rating')
            ->selectRaw('rating, count(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating')
            ->all();

        $histogram = [0, 0, 0, 0, 0];
        foreach ($rows as $rating => $count) {
            $rating = (int) $rating;
            if ($rating < 1 || $rating > 5) {
                continue;
            }
            $histogram[$rating - 1] = (int) $count;
        }

        return $histogram;
    }

    /**
     * @return array<int, array{date: string, count: int}>
     *                                                     Contiguous, zero-filled, ascending by `date`.
     */
    private function submissionsPerDay(Space $space, string $range): array
    {
        [$from, $to] = $this->windowBounds($space, $range);

        // PHPStan can't follow the match in dateExpression() back to a
        // literal type; the helper's return is a hard-coded SQL fragment
        // for every supported driver, so this is safe.
        $raw = $space->testimonials()
            ->live()
            ->whereBetween('submitted_at', [$from, $to])
            // @phpstan-ignore-next-line argument.type
            ->selectRaw($this->dateExpression().' as day, count(*) as c')
            ->groupBy('day')
            ->pluck('c', 'day')
            ->all();

        $out = [];
        $cursor = $from->copy();
        while ($cursor->lessThanOrEqualTo($to)) {
            $key = $cursor->toDateString();
            $out[] = [
                'date' => $key,
                'count' => (int) ($raw[$key] ?? 0),
            ];
            $cursor->addDay();
        }

        return $out;
    }

    /**
     * Driver-neutral `DATE(submitted_at)` expression. SQLite does not
     * support `DATE()` as a standalone function in the same way, so we
     * branch on the connection driver. The result column is always a
     * `YYYY-MM-DD` string in PHP-land thanks to Carbon's parsing.
     *
     * @phpstan-return non-empty-string
     */
    private function dateExpression(): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', submitted_at)",
            'mysql', 'mariadb' => 'DATE(submitted_at)',
            'pgsql' => "to_char(submitted_at, 'YYYY-MM-DD')",
            default => 'DATE(submitted_at)',
        };
    }
}
