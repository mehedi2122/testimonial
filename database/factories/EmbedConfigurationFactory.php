<?php

namespace Database\Factories;

use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmbedConfiguration>
 */
class EmbedConfigurationFactory extends Factory
{
    protected $model = EmbedConfiguration::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'layout' => EmbedLayout::Masonry,
            'dark_mode' => false,
            'animation_enabled' => true,
            'background_color' => null,
            'item_limit' => 12,
            'show_rating' => true,
        ];
    }
}
