<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'testimonial' => fake()->paragraph(),
            'rating' => fake()->numberBetween(3, 5),
            'consent_given' => true,
            'is_favorite' => false,
            'is_wall_of_love' => true,
            'is_hidden' => false,
            'submitted_at' => fake()->dateTimeBetween('-90 days', 'now'),
        ];
    }

    public function unconsented(): static
    {
        return $this->state(fn () => ['consent_given' => false]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_hidden' => true]);
    }

    public function notOnWall(): static
    {
        return $this->state(fn () => ['is_wall_of_love' => false]);
    }
}
