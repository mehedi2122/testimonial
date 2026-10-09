<?php

declare(strict_types=1);

namespace Tests\Feature\Spaces;

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the space-settings OpenSpec change. Mirrors the
 * SpaceCrudTest / InboxTest class style.
 *
 * Side benefit: these tests also lock the security-gap closure — every
 * space-scoped GET now 403s for a non-owner, not just `inbox`.
 */
class SpaceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_for_owner(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)
            ->get(route('spaces.settings', ['space' => $space->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/settings')
            ->where('space.slug', $space->slug)
            ->where('space.name', $space->name)
            ->where('space.theme', $space->theme->value)
            ->has('themes', 3)
        );
    }

    public function test_settings_page_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('spaces.settings', ['space' => $space->slug]))
            ->assertForbidden();
    }

    public function test_update_persists_changes_and_redirects_with_success(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create([
            'name' => 'Old name',
            'title' => 'Old title',
            'subtitle' => 'Old subtitle',
            'ask' => 'Old ask',
            'theme' => SpaceTheme::Minimal,
            'rating_enabled' => true,
        ]);

        $response = $this->actingAs($user)->patch(
            route('spaces.settings.update', ['space' => $space->slug]),
            [
                'name' => 'New name',
                'title' => 'New title',
                'subtitle' => 'New subtitle',
                'ask' => 'New ask copy',
                'theme' => SpaceTheme::Modern->value,
                'rating_enabled' => false,
            ],
        );

        $response->assertRedirect(route('spaces.settings', ['space' => $space->fresh()->slug], absolute: false));
        $response->assertSessionHas('success');

        $fresh = $space->fresh();
        $this->assertSame('New name', $fresh->name);
        $this->assertSame('new-name', $fresh->slug);
        $this->assertSame('New title', $fresh->title);
        $this->assertSame('New subtitle', $fresh->subtitle);
        $this->assertSame('New ask copy', $fresh->ask);
        $this->assertSame(SpaceTheme::Modern, $fresh->theme);
        $this->assertFalse($fresh->rating_enabled);
    }

    public function test_update_rejects_invalid_payload(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.settings.update', ['space' => $space->slug]),
            [
                'name' => 'a', // too short
                'title' => '',
                'ask' => '',
                'theme' => 'not_a_theme',
                'rating_enabled' => 'not-a-bool',
            ],
        );

        $response->assertSessionHasErrors(['name', 'title', 'ask', 'theme', 'rating_enabled']);
        $this->assertSame($space->name, $space->fresh()->name); // unchanged
    }

    public function test_update_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)->patch(
            route('spaces.settings.update', ['space' => $space->slug]),
            [
                'name' => 'Hijacked',
                'title' => 'Hijacked title',
                'subtitle' => null,
                'ask' => 'Hijacked ask',
                'theme' => SpaceTheme::Minimal->value,
                'rating_enabled' => true,
            ],
        )->assertForbidden();

        $this->assertSame($owner->id, $space->fresh()->user_id);
        $this->assertNotSame('Hijacked', $space->fresh()->name);
    }

    public function test_update_returns_404_for_soft_deleted_space(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->delete();

        $this->actingAs($user)->patch(
            route('spaces.settings.update', ['space' => $space->slug]),
            [
                'name' => 'Zombie',
                'title' => 'Zombie title',
                'subtitle' => null,
                'ask' => 'Zombie ask',
                'theme' => SpaceTheme::Minimal->value,
                'rating_enabled' => true,
            ],
        )->assertNotFound();
    }

    public function test_rename_redirects_to_new_slug(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create(['name' => 'Original']);

        $response = $this->actingAs($user)->patch(
            route('spaces.settings.update', ['space' => $space->slug]),
            [
                'name' => 'Renamed',
                'title' => $space->title,
                'subtitle' => $space->subtitle,
                'ask' => $space->ask,
                'theme' => $space->theme->value,
                'rating_enabled' => $space->rating_enabled,
            ],
        );

        $response->assertRedirect(
            route('spaces.settings', ['space' => 'renamed'], absolute: false)
        );
    }

    public function test_dashboard_and_embed_are_gated_by_view_policy(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        // Locks the security-gap closure: these GETs used to accept any
        // authenticated user who guessed a slug. Now they 403.
        $this->actingAs($other)
            ->get(route('spaces.dashboard', ['space' => $space->slug]))
            ->assertForbidden();
        $this->actingAs($other)
            ->get(route('spaces.embed', ['space' => $space->slug]))
            ->assertForbidden();
    }

    public function test_owner_can_configure_field_modes(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('spaces.settings.update', ['space' => $space->slug]), [
                'name' => $space->name,
                'title' => $space->title,
                'ask' => $space->ask,
                'theme' => SpaceTheme::Clean->value,
                'rating_enabled' => true,
                'fields' => [
                    'address' => 'optional',
                    'company_name' => 'required',
                    'profile_photo' => 'optional',
                    'not_a_field' => 'required',
                ],
            ])
            ->assertSessionHasNoErrors();

        $modes = $space->fields()->pluck('mode', 'field_key')->map->value->all();
        $this->assertSame('optional', $modes['address']);
        $this->assertSame('required', $modes['company_name']);
        $this->assertSame('optional', $modes['profile_photo']);
        $this->assertSame('off', $modes['social_url']);
        $this->assertArrayNotHasKey('not_a_field', $modes);
    }

    public function test_invalid_field_mode_is_rejected(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('spaces.settings.update', ['space' => $space->slug]), [
                'name' => $space->name,
                'title' => $space->title,
                'ask' => $space->ask,
                'theme' => SpaceTheme::Minimal->value,
                'rating_enabled' => true,
                'fields' => ['address' => 'sometimes'],
            ])
            ->assertSessionHasErrors('fields.address');
    }

    public function test_settings_page_lists_fields_with_modes(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->actingAs($user)
            ->get(route('spaces.settings', ['space' => $space->slug]))
            ->assertInertia(fn ($page) => $page
                ->where('fields.0.field_key', 'address')
                ->where('fields.0.mode', 'required')
            );
    }
}
