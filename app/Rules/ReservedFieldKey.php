<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects field_key values that collide with column names on `testimonials`
 * or `spaces` (OpenSpec change: public-testimonial-submission, group 3).
 *
 * A custom field with key `name` or `email` would either collide with the
 * column or get filtered by the embed API Resource whitelist, either of
 * which corrupts the inbox. Numbers chosen by the data-model section 31
 * structural guarantee: testimonial_values never holds an email.
 */
class ReservedFieldKey implements ValidationRule
{
    /**
     * Reserved keys (lower-case). The match is case-insensitive.
     *
     * @var list<string>
     */
    public const RESERVED = [
        'name',
        'email',
        'testimonial',
        'rating',
        'consent_given',
        'is_wall_of_love',
        'is_hidden',
        'is_favorite',
        'is_public',
        'public_id',
        'slug',
        'id',
        'user_id',
        'space_id',
        'submitted_at',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if (in_array(strtolower($value), self::RESERVED, true)) {
            $fail("The field_key ':value' is reserved and cannot be used for a custom field.");

            return;
        }
    }
}
