<?php

declare(strict_types=1);

namespace Tests\Feature\Spaces;

use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP coverage for the embed-builder OpenSpec change. Mirrors
 * SpaceSettingsTest / DashboardTest class style.
 *
 * Side benefit: locks the security-gap closure on the embed page
 * (GET 403 for non-owner) — and adds the same lock for the PATCH.
 */
class EmbedBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_embed_page_renders_for_owner_with_defaults_when_no_row(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertDatabaseCount('embed_configurations', 0);

        $response = $this->actingAs($user)
            ->get(route('spaces.embed', ['space' => $space->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('spaces/embed')
            ->where('space.slug', $space->slug)
            ->where('space.public_id', $space->public_id)
            ->where('embed.layout', EmbedLayout::Masonry->value)
            ->where('embed.dark_mode', false)
            ->where('embed.animation_enabled', true)
            ->where('embed.show_rating', true)
            ->where('embed.background_color', null)
            ->where('embed.item_limit', 12)
            ->has('layouts', 2)
            ->has('fields', 4)
            ->has('testimonials', 0)
            ->has('snippet')
        );
    }

    public function test_embed_page_reflects_saved_row(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'layout' => EmbedLayout::Carousel,
            'dark_mode' => true,
            'animation_enabled' => false,
            'show_rating' => false,
            'background_color' => '#0F172A',
            'item_limit' => 24,
        ]);

        $response = $this->actingAs($user)
            ->get(route('spaces.embed', ['space' => $space->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('embed.layout', 'carousel')
            ->where('embed.dark_mode', true)
            ->where('embed.animation_enabled', false)
            ->where('embed.show_rating', false)
            ->where('embed.background_color', '#0F172A')
            ->where('embed.item_limit', 24)
        );
    }

    public function test_embed_page_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)
            ->get(route('spaces.embed', ['space' => $space->slug]))
            ->assertForbidden();
    }

    public function test_patch_creates_row_for_owner(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $this->assertDatabaseCount('embed_configurations', 0);

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'carousel',
                'dark_mode' => true,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => '#0F172A',
                'item_limit' => 24,
            ],
        );

        $response->assertRedirect(
            route('spaces.embed', ['space' => $space->slug], absolute: false),
        );
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('embed_configurations', 1);
        $config = $space->fresh()->embedConfiguration;
        $this->assertSame(EmbedLayout::Carousel, $config->layout);
        $this->assertSame(24, $config->item_limit);
        $this->assertSame('#0F172A', $config->background_color);
    }

    public function test_patch_updates_existing_row(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'layout' => EmbedLayout::Masonry,
            'item_limit' => 12,
        ]);

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'carousel',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 8,
            ],
        );

        $response->assertRedirect(
            route('spaces.embed', ['space' => $space->slug], absolute: false),
        );

        $this->assertDatabaseCount('embed_configurations', 1);
        $config = $space->fresh()->embedConfiguration;
        $this->assertSame(EmbedLayout::Carousel, $config->layout);
        $this->assertSame(8, $config->item_limit);
        $this->assertNull($config->background_color);
    }

    public function test_patch_returns_403_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $space = Space::factory()->for($owner)->create();

        $this->actingAs($other)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'carousel',
                'dark_mode' => true,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 24,
            ],
        )->assertForbidden();

        $this->assertDatabaseCount('embed_configurations', 0);
    }

    public function test_patch_rejects_invalid_layout(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'not-a-layout',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 12,
            ],
        );

        $response->assertSessionHasErrors('layout');
        $this->assertDatabaseCount('embed_configurations', 0);
    }

    public function test_patch_rejects_invalid_background_color(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => 'red',
                'item_limit' => 12,
            ],
        );

        $response->assertSessionHasErrors('background_color');
        $this->assertDatabaseCount('embed_configurations', 0);
    }

    public function test_patch_rejects_item_limit_below_min(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 0,
            ],
        );

        $response->assertSessionHasErrors('item_limit');
        $this->assertDatabaseCount('embed_configurations', 0);
    }

    public function test_patch_rejects_item_limit_above_max(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 51,
            ],
        );

        $response->assertSessionHasErrors('item_limit');
        $this->assertDatabaseCount('embed_configurations', 0);
    }

    public function test_patch_with_unknown_field_key_does_not_error(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $response = $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 12,
                'field_visibility' => [
                    'company_name' => true,
                    '__proto__' => true,
                ],
            ],
        );

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(
            route('spaces.embed', ['space' => $space->slug], absolute: false),
        );

        $this->assertDatabaseCount('embed_configurations', 1);
        $this->assertSame(4, $space->fields()->count());
    }

    public function test_field_visibility_round_trip(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // Turn company_name off.
        $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => null,
                'item_limit' => 12,
                'field_visibility' => [
                    'company_name' => false,
                    'social_url' => true,
                    'profile_photo' => true,
                ],
            ],
        )->assertSessionHasNoErrors();

        $this->assertFalse(
            $space->fields()->where('field_key', 'company_name')->value('show_in_embed'),
        );

        // GET reflects it.
        $response = $this->actingAs($user)
            ->get(route('spaces.embed', ['space' => $space->slug]));
        $response->assertInertia(fn ($page) => $page
            // fields.0 is Address (sort_order 5, private by default).
            ->where('fields.0.key', 'address')
            ->where('fields.0.show_in_embed', false)
            ->where('fields.1.key', 'company_name')
            ->where('fields.1.show_in_embed', false)
        );
    }

    public function test_empty_background_color_clears_the_field(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'background_color' => '#0F172A',
        ]);

        $this->actingAs($user)->patch(
            route('spaces.embed.update', ['space' => $space->slug]),
            [
                'layout' => 'masonry',
                'dark_mode' => false,
                'animation_enabled' => true,
                'show_rating' => true,
                'background_color' => '',
                'item_limit' => 12,
            ],
        )->assertSessionHasNoErrors();

        $this->assertNull($space->fresh()->embedConfiguration->background_color);
    }
}
