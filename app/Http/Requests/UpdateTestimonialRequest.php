<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the owner-side testimonial edit form (OpenSpec change:
 * testimonial-inbox).
 *
 * Email is intentionally absent — that is the §15 data-model guarantee
 * (the submission email is the canonical contact; locking it prevents
 * an owner from "laundering" a testimonial to a different address).
 *
 * Length bounds mirror `SubmitTestimonialRequest` for the body so the
 * stored shape is identical regardless of who wrote it.
 */
class UpdateTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization is checked inside the controller via Gate::authorize
        // against the loaded instance, where we have access to the Space
        // ownership context. FormRequest::authorize() only sees the user.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'testimonial' => ['required', 'string', 'min:10', 'max:2000'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
