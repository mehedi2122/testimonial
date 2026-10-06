<?php

namespace Database\Factories;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceFieldType;
use App\Models\Space;
use App\Models\SpaceField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpaceField>
 */
class SpaceFieldFactory extends Factory
{
    protected $model = SpaceField::class;

    public function definition(): array
    {
        $key = Str::lower(fake()->word());

        return [
            'space_id' => Space::factory(),
            'field_key' => $key,
            'label' => ucwords($key),
            'type' => SpaceFieldType::Text,
            'mode' => SpaceFieldMode::Off,
            'sort_order' => 0,
            'show_in_embed' => true,
        ];
    }
}