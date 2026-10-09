<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the authenticated Space-create form (OpenSpec change:
 * space-crud). Mirrors {@see SubmitTestimonialRequest} style — strict
 * types, docblocks per public method, custom messages for the user-facing
 * failure shapes.
 *
 * Authorization is implicit: the route group already enforces
 * `auth + verified` middleware, so anyone reaching the controller is
 * authorized to attempt a Space create. The plan-limit preflight is run
 * inside `CreateSpaceAction::checkPlanLimit()` before persistence.
 */
class CreateSpaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'title' => ['required', 'string', 'min:2', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:200'],
            'ask' => ['required', 'string', 'min:5', 'max:500'],
            'theme' => ['required', Rule::enum(SpaceTheme::class)],
            'rating_enabled' => ['required', 'boolean'],
            // PRD §8 field configuration: field_key => off|optional|required.
            'fields' => ['sometimes', 'array'],
            'fields.*' => [Rule::enum(SpaceFieldMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Give your Space a name so you can find it later.',
            'name.min' => 'Use at least 2 characters for the Space name.',
            'title.required' => 'Add a short title respondents will recognize.',
            'ask.required' => 'Add an ask. Respondents need a reason to submit.',
            'theme.Illuminate\\Validation\\Rules\\Enum' => 'Pick one of the available themes.',
        ];
    }
}
