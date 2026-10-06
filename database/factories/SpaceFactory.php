<?php

namespace Database\Factories;

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    public function definition(): array
    {
        $words = fake()->unique()->words(3);
        $name = is_array($words) ? implode(' ', $words) : $words;

        return [
            'user_id' => User::factory(),
            'name' => ucwords($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'public_id' => Str::lower(Str::random(12)),
            'title' => ucwords($name),
            'subtitle' => fake()->sentence(),
            'ask' => fake()->paragraph(),
            'theme' => SpaceTheme::MinimalLight,
            'rating_enabled' => true,
        ];
    }
}
