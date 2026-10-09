<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SpaceFieldType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Per-type value validation for testimonial submissions (OpenSpec change:
 * public-testimonial-submission, group 4).
 *
 * Maps `space_fields.type` to a value-shape check:
 *   - text: string, <= 500 chars
 *   - url: valid URL per FILTER_VALIDATE_URL, <= 2048 chars
 *   - number: numeric, between -1e9 and 1e9
 *   - image: string, <= 255 chars (full size/type cap deferred per data-model §9.3)
 *   - email: never permitted (structural §31 — testimonial_values never holds an email)
 *
 * `rating` is not in the SpaceFieldType enum, so it cannot reach this rule on a
 * custom field.
 */
class SubmissionValue implements ValidationRule
{
    /**
     * Hard caps from the data-model contract. Hard-coded here because they are
     * spec-level constants, not configuration.
     */
    private const TEXT_MAX_LENGTH = 500;

    private const URL_MAX_LENGTH = 2048;

    private const IMAGE_MAX_LENGTH = 255;

    private const NUMBER_MIN = -1_000_000_000;

    private const NUMBER_MAX = 1_000_000_000;

    public function __construct(private readonly SpaceFieldType $type) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        match ($this->type) {
            SpaceFieldType::Text => $this->validateText($value, $fail),
            SpaceFieldType::Url => $this->validateUrl($value, $fail),
            SpaceFieldType::Number => $this->validateNumber($value, $fail),
            SpaceFieldType::Image => $this->validateImage($value, $fail),
            SpaceFieldType::Email => $fail('Email-typed custom fields are not permitted.'),
        };
    }

    private function validateText(mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The value must be a string.');

            return;
        }

        if (mb_strlen($value) > self::TEXT_MAX_LENGTH) {
            $fail('The value must be at most '.self::TEXT_MAX_LENGTH.' characters.');
        }
    }

    private function validateUrl(mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The value must be a string.');

            return;
        }

        if (mb_strlen($value) > self::URL_MAX_LENGTH) {
            $fail('The URL must be at most '.self::URL_MAX_LENGTH.' characters.');

            return;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            $fail('The value must be a valid URL.');

            return;
        }

        // FILTER_VALIDATE_URL accepts `javascript://…` and `data:`; these
        // are rendered as links on public pages, so only web URLs pass.
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            $fail('The URL must start with http:// or https://.');
        }
    }

    private function validateNumber(mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            $fail('The value must be numeric.');

            return;
        }

        $number = (float) $value;

        if ($number < self::NUMBER_MIN || $number > self::NUMBER_MAX) {
            $fail('The number must be between '.self::NUMBER_MIN.' and '.self::NUMBER_MAX.'.');
        }
    }

    private function validateImage(mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The image value must be a string path.');

            return;
        }

        if (mb_strlen($value) > self::IMAGE_MAX_LENGTH) {
            $fail('The image path must be at most '.self::IMAGE_MAX_LENGTH.' characters.');
        }
    }
}
