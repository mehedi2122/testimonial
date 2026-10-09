<?php

declare(strict_types=1);

namespace Tests\Feature\Spaces;

use App\Actions\Dashboards\DashboardAnalyticsAction;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the dashboard-analytics OpenSpec change. Mirrors
 * the SpaceCrudTest / InboxTest / SpaceSettingsTest class style.
 *
 * Side benefit: continues the security-gap lock for the dashboard GET.
 * (Group 17 already shipped `Gate::authorize('view', $space)` here.)
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_for_owner_with_default_30d_range(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(3)->create();

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/dashboard')
            ->where('space.slug', $space->slug)
            ->where('analytics.range', '30d')
            ->where('analytics.total', 3)
            ->where('analytics.plan', 'free')
            ->where('analytics.plan_limit', 100)
            ->where('analytics.live_count', 3)
        );
    }

    public function test_dashboard_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('spaces.dashboard', ['space' => $space->slug]))
            ->assertForbidden();
    }

    public function test_dashboard_7d_range_returns_seven_buckets(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(2)->create();

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug, 'range' => '7d']));

        $response->assertInertia(fn ($page) => $page
            ->where('analytics.range', '7d')
            ->has('analytics.submissions_per_day', 7)
        );
    }

    public function test_dashboard_invalid_range_normalizes_to_30d(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug, 'range' => 'yesterday']));

        $response->assertInertia(fn ($page) => $page
            ->where('analytics.range', DashboardAnalyticsAction::RANGE_30D)
            ->has('analytics.submissions_per_day', 30)
        );
    }

    public function test_dashboard_free_user_sees_upgrade_banner_copy(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        Testimonial::factory()->for($space)->count(2)->create();

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug]));

        $response->assertInertia(fn ($page) => $page
            ->where('analytics.plan', 'free')
            ->where('analytics.plan_limit', 100)
            ->where('analytics.live_count', 2)
        );
        // The page itself decides whether to show the banner; the prop
        // is what the React component reads. Locking the prop shape here.
    }

    public function test_dashboard_average_rating_and_histogram_when_enabled(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => true]);
        Testimonial::factory()->for($space)->count(2)->create(['rating' => 5]);
        Testimonial::factory()->for($space)->count(3)->create(['rating' => 4]);

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug]));

        // avg(5,5,4,4,4) = 4.4
        $response->assertInertia(fn ($page) => $page
            ->where('analytics.rating_enabled', true)
            ->where('analytics.average_rating', 4.4)
            ->where('analytics.rating_histogram', [0, 0, 0, 3, 2])
        );
    }

    public function test_dashboard_average_rating_null_when_disabled(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => false]);
        Testimonial::factory()->for($space)->count(3)->create(['rating' => 5]);

        $response = $this->actingAs($user)
            ->get(route('spaces.dashboard', ['space' => $space->slug]));

        $response->assertInertia(fn ($page) => $page
            ->where('analytics.rating_enabled', false)
            ->where('analytics.average_rating', null)
        );
    }
}
