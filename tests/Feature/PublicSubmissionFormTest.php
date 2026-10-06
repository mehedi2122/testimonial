<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SpaceFieldMode;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the public submission form page (OpenSpec change:
 * public-submission-form). Mirrors PublicSubmissionEndpointTest style for
 * end-user behaviour at /s/{public_id}.
 */
class PublicSubmissionFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_shows_form_for_live_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create([
            'title' => 'Customer wins',
            'subtitle' => 'Real feedback',
            'ask' => 'What do you think?',
        ]);

        $response = $this->get(route('public.submissions.show', ['public_id' => $space->public_id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('public/submit')
            ->where('space.title', 'Customer wins')
            ->where('space.subtitle', 'Real feedback')
            ->where('space.ask', 'What do you think?')
            ->where('space.public_id', $space->public_id)
            ->where('space.rating_enabled', true)
        );
    }

    public function test_get_renders_only_non_off_fields(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        // Promote only company_name; leave social_url and profile_photo Off.
        $space->fields()->where('field_key', 'company_name')->update([
            'mode' => SpaceFieldMode::Optional,
        ]);

        $response = $this->get(route('public.submissions.show', ['public_id' => $space->public_id]));

        $response->assertInertia(fn ($page) => $page
            ->where('fields', fn ($fields) => collect($fields)
                ->pluck('field_key')
                ->all() === ['company_name']
            )
        );
    }

    public function test_get_includes_rating_flag_when_enabled(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['rating_enabled' => true]);

        $response = $this->get(route('public.submissions.show', ['public_id' => $space->public_id]));

        $response->assertInertia(fn ($page) => $page
            ->where('space.rating_enabled', true)
        );
    }

    public function test_get_returns_404_for_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $this->get(route('public.submissions.show', ['public_id' => $space->public_id]))
            ->assertNotFound();
    }

    public function test_get_returns_404_for_unknown_public_id(): void
    {
        $this->get(route('public.submissions.show', ['public_id' => 'does-not-exist']))
            ->assertNotFound();
    }

    public function test_get_does_not_require_authentication(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // No actingAs — the route is public.
        $response = $this->get(route('public.submissions.show', ['public_id' => $space->public_id]));

        $response->assertOk();
    }

    public function test_show_endpoint_does_not_count_against_throttle(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // GET the page 5 times in a row — should never 429.
        for ($i = 0; $i < 5; $i++) {
            $this->get(route('public.submissions.show', ['public_id' => $space->public_id]))
                ->assertOk();
        }
    }
}
