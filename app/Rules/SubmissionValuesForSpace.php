<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\SpaceFieldType;
use App\Models\Space;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Composite rule validating the entire `values` array against the resolved
 * Space's space_fields configuration (OpenSpec change: public-testimonial-submission,
 * group 4).
 *
 * For each value entry:
 *   1. Look up the field_key on the Space's space_fields table.
 *   2. If unknown to this Space, reject (cross-space leak guard from the allowlist).
 *   3. Apply the per-type SubmissionValue check.
 *
 * Laravel's DataAwareRule interface gives us access to the full payload,
 * including sibling `values` entries, so we can validate holistically.
 */
class SubmissionValuesForSpace implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, string>  $fieldTypeMap  field_key (lowercased) -> SpaceFieldType value
     */
    public function __construct(private readonly array $fieldTypeMap) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be an array.');

            return;
        }

        foreach ($value as $index => $entry) {
            if (! is_array($entry) || ! isset($entry['field_key'])) {
                $fail("Entry {$index} is missing a field_key.");

                continue;
            }

            $fieldKey = strtolower((string) $entry['field_key']);

            if (! array_key_exists($fieldKey, $this->fieldTypeMap)) {
                $fail("Field '{$entry['field_key']}' is not defined on this Space.");

                continue;
            }

            $type = SpaceFieldType::from($this->fieldTypeMap[$fieldKey]);
            $entryValue = $entry['value'] ?? null;

            $inner = new SubmissionValue($type);
            $inner->validate("{$attribute}.{$index}.value", $entryValue, $fail);
        }
    }
}
