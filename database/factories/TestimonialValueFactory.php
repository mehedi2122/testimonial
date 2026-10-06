<?php

namespace Database\Factories;

use App\Models\SpaceField;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestimonialValue>
 */
class TestimonialValueFactory extends Factory
{
    protected $model = TestimonialValue::class;

    public function definition(): array
    {
        return [
            'testimonial_id' => Testimonial::factory(),
            'space_field_id' => SpaceField::factory(),
            'value' => fake()->sentence(),
        ];
    }
}