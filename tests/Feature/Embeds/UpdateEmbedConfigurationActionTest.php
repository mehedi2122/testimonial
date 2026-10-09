<?php

declare(strict_types=1);

namespace Tests\Feature\Embeds;

use App\Actions\Embeds\UpdateEmbedConfigurationAction;
use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Action-level coverage for embed-builder. Mirrors
 * UpdateSpaceSettingsActionTest style — action in isolation, no HTTP.
 */
class UpdateEmbedConfigurationActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_row_when_none_exists(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $this->assertDatabaseCount('embed_configurations', 0);

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Carousel->value,
            'dark_mode' => true,
            'animation_enabled' => false,
            'show_rating' => false,
            'background_color' => '#0F172A',
            'item_limit' => 24,
        ]);

        $this->assertDatabaseCount('embed_configurations', 1);
        $this->assertSame($space->id, $config->space_id);
        $this->assertSame(EmbedLayout::Carousel, $config->layout);
        $this->assertTrue($config->dark_mode);
        $this->assertFalse($config->animation_enabled);
        $this->assertFalse($config->show_rating);
        $this->assertSame('#0F172A', $config->background_color);
        $this->assertSame(24, $config->item_limit);
    }

    public function test_updates_existing_row(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'layout' => EmbedLayout::Masonry,
            'item_limit' => 12,
        ]);
        $this->assertDatabaseCount('embed_configurations', 1);

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Carousel->value,
            'dark_mode' => true,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 8,
        ]);

        $this->assertDatabaseCount('embed_configurations', 1);
        $this->assertSame(EmbedLayout::Carousel, $config->layout);
        $this->assertSame(8, $config->item_limit);
    }

    public function test_normalizes_lowercase_hex_to_uppercase(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => '#abcdef',
            'item_limit' => 12,
        ]);

        $this->assertSame('#ABCDEF', $config->background_color);
    }

    public function test_empty_background_color_becomes_null(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        EmbedConfiguration::factory()->for($space)->create([
            'background_color' => '#000000',
        ]);

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => '',
            'item_limit' => 12,
        ]);

        $this->assertNull($config->background_color);
    }

    public function test_clamps_item_limit_above_max(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 1000,
        ]);

        $this->assertSame(EmbedConfiguration::MAX_ITEM_LIMIT, $config->item_limit);
    }

    public function test_clamps_item_limit_below_min(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $config = (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => -5,
        ]);

        $this->assertSame(1, $config->item_limit);
    }

    public function test_field_visibility_syncs_space_fields(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        // Space::created seeds 3 predefined fields. Verify they're all
        // show_in_embed=true initially.
        $this->assertSame(4, $space->fields()->count());
        $this->assertSame(
            3,
            $space->fields()->where('show_in_embed', true)->count(),
        );

        (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 12,
            'field_visibility' => [
                'company_name' => false,
                'social_url' => true,
                'profile_photo' => false,
            ],
        ]);

        $this->assertFalse(
            $space->fields()->where('field_key', 'company_name')->value('show_in_embed'),
        );
        $this->assertTrue(
            $space->fields()->where('field_key', 'social_url')->value('show_in_embed'),
        );
        $this->assertFalse(
            $space->fields()->where('field_key', 'profile_photo')->value('show_in_embed'),
        );
    }

    public function test_unknown_field_key_is_ignored(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();

        $this->assertSame(4, $space->fields()->count());

        (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 12,
            'field_visibility' => [
                'company_name' => true,
                '__proto__' => true,
                'admin' => true,
            ],
        ]);

        // No new rows created.
        $this->assertSame(4, $space->fields()->count());
        // The known key still applied.
        $this->assertTrue(
            $space->fields()->where('field_key', 'company_name')->value('show_in_embed'),
        );
    }

    public function test_empty_field_visibility_leaves_existing_values_untouched(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->fields()->where('field_key', 'company_name')->update([
            'show_in_embed' => false,
        ]);

        (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 12,
            'field_visibility' => [],
        ]);

        $this->assertFalse(
            $space->fields()->where('field_key', 'company_name')->value('show_in_embed'),
        );
    }

    public function test_partial_field_visibility_leaves_omitted_keys_untouched(): void
    {
        $user = User::factory()->create();
        $space = Space::factory()->for($user)->create();
        $space->fields()->where('field_key', 'company_name')->update([
            'show_in_embed' => false,
        ]);

        (new UpdateEmbedConfigurationAction)->run($space, [
            'layout' => EmbedLayout::Masonry->value,
            'dark_mode' => false,
            'animation_enabled' => true,
            'show_rating' => true,
            'background_color' => null,
            'item_limit' => 12,
            'field_visibility' => [
                'social_url' => false,
            ],
        ]);

        // company_name was omitted, kept its prior value.
        $this->assertFalse(
            $space->fields()->where('field_key', 'company_name')->value('show_in_embed'),
        );
        $this->assertFalse(
            $space->fields()->where('field_key', 'social_url')->value('show_in_embed'),
        );
    }
}
