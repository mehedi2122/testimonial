<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Dashboards\DashboardAnalyticsAction;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Action-level coverage for the dashboard-analytics OpenSpec change.
 * Mirrors the InboxActionTest / UpdateSpaceSettingsActionTest class style.
 */
class DashboardAnalyticsActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_and_favorite_and_wall_counts(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(3)->create(['is_favorite' => false, 'is_wall_of_love' => true]);
        Testimonial::factory()->for($space)->count(2)->favorite()->create(['is_wall_of_love' => true]);
        Testimonial::factory()->for($space)->count(1)->notOnWall()->create();

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertSame(6, $analytics['total']);
        $this->assertSame(2, $analytics['favorites']);
        $this->assertSame(5, $analytics['on_wall']);
        $this->assertSame(0, $analytics['hidden']);
    }

    public function test_average_rating_and_histogram_with_mixed_ratings(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => true]);
        $ratings = [5, 5, 4, 4, 4, 3, 3, 2, 1, 5];
        foreach ($ratings as $r) {
            Testimonial::factory()->for($space)->create(['rating' => $r]);
        }

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        // avg(5,5,4,4,4,3,3,2,1,5) = 36/10 = 3.6
        $this->assertEqualsWithDelta(3.6, $analytics['average_rating'], 0.0001);
        // 1★=1, 2★=1, 3★=2, 4★=3, 5★=3
        $this->assertSame([1, 1, 2, 3, 3], $analytics['rating_histogram']);
    }

    public function test_average_rating_is_null_when_rating_disabled(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => false]);
        Testimonial::factory()->for($space)->count(3)->create(['rating' => 5]);

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertNull($analytics['average_rating']);
        $this->assertSame([0, 0, 0, 0, 0], $analytics['rating_histogram']);
    }

    public function test_average_rating_is_null_when_no_rated_rows(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => true]);
        Testimonial::factory()->for($space)->count(3)->create(['rating' => null]);

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertNull($analytics['average_rating']);
    }

    public function test_soft_deleted_testimonial_is_excluded(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(2)->create();
        $gone = Testimonial::factory()->for($space)->create();
        $gone->delete();

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertSame(2, $analytics['total']);
    }

    public function test_seven_day_window_returns_seven_buckets(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(5)->create([
            'submitted_at' => Carbon::now()->subDays(2),
        ]);

        $analytics = (new DashboardAnalyticsAction)->for($space, '7d');

        $this->assertSame('7d', $analytics['range']);
        $this->assertCount(7, $analytics['submissions_per_day']);
        // Each bucket has a YYYY-MM-DD date string.
        foreach ($analytics['submissions_per_day'] as $bucket) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $bucket['date']);
            $this->assertIsInt($bucket['count']);
        }
    }

    public function test_thirty_day_window_returns_thirty_buckets(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertCount(30, $analytics['submissions_per_day']);
    }

    public function test_ninety_day_window_returns_ninety_buckets(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $analytics = (new DashboardAnalyticsAction)->for($space, '90d');

        $this->assertCount(90, $analytics['submissions_per_day']);
    }

    public function test_all_time_window_starts_from_earliest_testimonial(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $earliest = Testimonial::factory()->for($space)->create([
            'submitted_at' => Carbon::now()->subDays(120),
        ]);

        $analytics = (new DashboardAnalyticsAction)->for($space, 'all');

        $this->assertSame('all', $analytics['range']);
        $this->assertGreaterThanOrEqual(120, count($analytics['submissions_per_day']));
        $this->assertSame(
            $earliest->submitted_at->toDateString(),
            $analytics['submissions_per_day'][0]['date'],
        );
    }

    public function test_invalid_range_normalizes_to_thirty_days(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $analytics = (new DashboardAnalyticsAction)->for($space, 'yesterday');

        $this->assertSame('30d', $analytics['range']);
        $this->assertCount(30, $analytics['submissions_per_day']);
    }

    public function test_plan_and_plan_limit_mirror_owner(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertSame('free', $analytics['plan']);
        $this->assertSame(100, $analytics['plan_limit']);
    }

    public function test_submissions_per_day_sums_match_total(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(7)->create([
            'submitted_at' => Carbon::now()->subDays(2),
        ]);
        Testimonial::factory()->for($space)->count(3)->create([
            'submitted_at' => Carbon::now()->subDays(10),
        ]);
        // Out-of-window row (older than 30d) — counted in `total` but not
        // in the 30d window. The action treats `total` as all-time live.
        Testimonial::factory()->for($space)->create([
            'submitted_at' => Carbon::now()->subDays(60),
        ]);

        $analytics = (new DashboardAnalyticsAction)->for($space, '30d');

        $this->assertSame(11, $analytics['total']);
        $sum = array_sum(array_column($analytics['submissions_per_day'], 'count'));
        $this->assertSame(10, $sum);
    }
}
