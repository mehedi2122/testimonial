<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Space;
use App\Rules\ReservedFieldKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Base validation for the public testimonial submission endpoint (OpenSpec change:
 * public-testimonial-submission, group 2). Subsequent groups (3, 4, 5) add custom rules for
 * `values.*.field_key` (reserved keys) and `values.*.value` (per-space_fields.type shape).
 *
 * `consent_given` is a hard rule (must be true) because the data-model section 15 gate
 * requires explicit consent before any testimonial can be stored with the respondent's
 * identity attached.
 *
 * `rating` is conditional on the Space's `rating_enabled` flag. The Space is resolved via
 * the route parameter and reused by subsequent groups (6/7) for SoftDeletes preflight and
 * plan-limit checks.
 */
class SubmitTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Resolve the Space from the route parameter. Reused by rating-conditional logic and
     * by subsequent groups (6/7) that need the Space to enforce SoftDeletes preflight and
     * plan-limit checks.
     */
    public function space(): Space
    {
        /** @var Space $space */
        $space = Space::query()
            ->where('public_id', $this->route('public_id'))
            ->whereNull('deleted_at')
            ->firstOrFail();

        return $space;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $space = $this->route('public_id')
            ? Space::query()->where('public_id', $this->route('public_id'))->whereNull('deleted_at')->first()
            : null;

        $ratingRequired = $space instanceof Space && $space->rating_enabled;

        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc'],
            'testimonial' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => [$ratingRequired ? 'required' : 'sometimes', 'integer', 'min:1', 'max:5'],
            'consent_given' => ['required', 'boolean', Rule::in([true])],
            'values' => ['sometimes', 'array'],
            'values.*' => ['array:field_key,value'],
            'values.*.field_key' => ['required', 'string', 'max:64', new ReservedFieldKey],
            'values.*.value' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent_given.in' => 'Social sharing consent is required to submit a testimonial.',
            'values.*.field_key.required' => 'Each submission value must include a field_key.',
            'values.*.value.required' => 'Each submission value must include a value.',
        ];
    }
}
