<?php

declare(strict_types=1);

namespace App\Http\Requests\Spaces;

use App\Enums\EmbedLayout;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the Embed Builder form (OpenSpec change: embed-builder).
 *
 * Mirrors the rest of the FormRequest family: `authorize() === true`
 * because the controller already calls `Gate::authorize('updateEmbed',
 * $space)` once route model binding has resolved the Space by slug.
 *
 * `field_visibility` is intentionally permissive: any string key with
 * a boolean value passes the regex / type check here. The action does
 * the per-Space scoping (it filters against live `space_fields` rows,
 * ignoring unknown keys — defends against tampered payloads).
 */
class UpdateEmbedConfigurationRequest extends FormRequest
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
            'layout' => ['required', Rule::enum(EmbedLayout::class)],
            'dark_mode' => ['required', 'boolean'],
            'animation_enabled' => ['required', 'boolean'],
            'show_rating' => ['required', 'boolean'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'item_limit' => ['required', 'integer', 'min:1', 'max:'.EmbedConfiguration::MAX_ITEM_LIMIT],
            'field_visibility' => ['sometimes', 'array'],
            'field_visibility.*' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'background_color.regex' => 'Pick a hex color like #0F172A or leave it empty.',
            'item_limit.max' => 'Embeds are capped at '.EmbedConfiguration::MAX_ITEM_LIMIT.' testimonials.',
        ];
    }

    /**
     * Strip empty-string background_color to null so the action's
     * `null | uppercase` normalization is the only path. The validation
     * regex already rejects invalid hex, so by the time the action
     * sees the value it is either null, missing, or a valid 7-char hex.
     *
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $data = $this->validated();

        if (array_key_exists('background_color', $data) && $data['background_color'] === '') {
            $data['background_color'] = null;
        }

        return $data;
    }
}
