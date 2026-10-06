<?php

declare(strict_types=1);

namespace Tests\Feature\Spaces;

use App\Enums\Plan;
use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the space-crud OpenSpec change. Mirrors the
 * PostLoginRedirectTest class-based Pest style.
 *
 * Gating notes:
 *  - The /spaces tree is auth+verified, so tests mark the user verified
 *    via the UserFactory (verified by default).
 *  - Plan resolution goes through Cashier; tests stay on Free (default).
 */
class SpaceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('spaces.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/create')
            ->has('themes', 3)
            ->where('themes.0.value', SpaceTheme::MinimalLight->value)
        );
    }

    public function test_store_creates_space_and_redirects_to_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('spaces.store'), [
            'name' => 'Shiplog Reviews',
            'title' => 'What people say',
            'subtitle' => null,
            'ask' => 'Tell us about your experience',
            'theme' => SpaceTheme::MinimalDark->value,
            'rating_enabled' => true,
        ]);

        $response->assertRedirect(route('spaces.dashboard', ['space' => 'shiplog-reviews'], absolute: false));
        $response->assertSessionHas('success');

        $space = $user->spaces()->first();
        $this->assertNotNull($space);
        $this->assertSame('Shiplog Reviews', $space->name);
        $this->assertSame('shiplog-reviews', $space->slug);
        $this->assertSame(SpaceTheme::MinimalDark, $space->theme);
    }

    public function test_store_redirects_to_index_with_error_at_plan_cap(): void
    {
        $user = User::factory()->create();
        Space::factory()->for($user)->count(Plan::Free->maxSpaces())->create();

        $response = $this->actingAs($user)->post(route('spaces.store'), [
            'name' => 'One More',
            'title' => 'New Space',
            'ask' => 'Tell us about it',
            'theme' => SpaceTheme::MinimalLight->value,
            'rating_enabled' => true,
        ]);

        $response->assertRedirect(route('spaces.index', absolute: false));
        $response->assertSessionHas('error');
        $this->assertSame(Plan::Free->maxSpaces(), $user->spaces()->count());
    }

    public function test_store_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('spaces.store'), [
            'name' => 'a', // too short
            'title' => '',
            'ask' => '',
            'theme' => 'not_a_theme',
            'rating_enabled' => 'not-a-bool',
        ]);

        $response->assertSessionHasErrors(['name', 'title', 'ask', 'theme', 'rating_enabled']);
        $this->assertSame(0, $user->spaces()->count());
    }

    public function test_destroy_soft_deletes_owned_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->delete(route('spaces.destroy', ['space' => $space->slug]));

        $response->assertRedirect(route('spaces.index', absolute: false));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('spaces', ['id' => $space->id]);
    }

    public function test_destroy_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $response = $this->actingAs($other)->delete(route('spaces.destroy', ['space' => $space->slug]));

        $response->assertForbidden();
        $this->assertDatabaseHas('spaces', ['id' => $space->id, 'deleted_at' => null]);
    }

    public function test_index_renders_user_spaces(): void
    {
        $user = User::factory()->create();
        $a = Space::factory()->for($user)->create(['name' => 'Alpha']);
        $b = Space::factory()->for($user)->create(['name' => 'Bravo']);

        $response = $this->actingAs($user)->get(route('spaces.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/index')
            ->where('spaces', fn ($spaces) => collect($spaces)->pluck('slug')->contains($a->slug)
                && collect($spaces)->pluck('slug')->contains($b->slug)
            )
        );
    }

    public function test_index_excludes_soft_deleted_spaces(): void
    {
        $user = User::factory()->create();
        $alive = Space::factory()->for($user)->create(['name' => 'Alive']);
        $gone = Space::factory()->for($user)->create(['name' => 'Gone']);
        $gone->delete();

        $response = $this->actingAs($user)->get(route('spaces.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('spaces', fn ($spaces) => collect($spaces)->pluck('slug')->contains($alive->slug)
                && ! collect($spaces)->pluck('slug')->contains($gone->slug)
            )
        );
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get(route('spaces.create'))->assertRedirect(route('login'));
        $this->get(route('spaces.index'))->assertRedirect(route('login'));
    }
}
