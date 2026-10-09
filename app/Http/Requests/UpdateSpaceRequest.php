<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\SpaceFieldMode;
use App\Enums\SpaceTheme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the authenticated Space-settings edit form (OpenSpec change:
 * space-settings). Bounds and messages mirror {@see CreateSpaceRequest}
 * exactly so the create and update flows surface identical failure copy
 * for the same payload shape.
 *
 * Authorization is implicit: the controller calls
 * `Gate::authorize('update', $space)` after route-model binding resolves
 * the Space by slug, so this FormRequest only needs to declare
 * `authorize() === true`.
 */
class UpdateSpaceRequest extends FormRequest
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
