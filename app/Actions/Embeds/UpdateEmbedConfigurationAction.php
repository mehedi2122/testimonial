<?php

declare(strict_types=1);

namespace App\Actions\Embeds;

use App\Http\Requests\Spaces\UpdateEmbedConfigurationRequest;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use App\Models\SpaceField;
use Illuminate\Support\Facades\DB;

/**
 * Owner-side write path for the Embed Builder (OpenSpec change:
 * embed-builder). Single Responsibility: turn a validated form payload
 * into a saved `embed_configurations` row + synced `space_fields`
 * visibility flags.
 *
 * Mirrors the `app/Actions/{Inbox,Dashboards,Public}/` pattern: one
 * public `run` method, controller + FormRequest are thin.
 *
 * Normalization rules:
 *   - `background_color` is stored as uppercase 7-char `#RRGGBB` or null.
 *     Empty / null / missing → null. Lowercase hex → uppercase.
 *     Invalid hex never reaches this class (FormRequest regex).
 *   - `item_limit` is clamped to 1..MAX_ITEM_LIMIT. Defence in depth —
 *     the FormRequest already caps it, so this clamp only fires for
 *     future direct callers (artisan commands, tests).
 *   - `field_visibility` is filtered against the Space's live
 *     `space_fields` rows. Unknown `field_key`s are silently ignored —
 *     defends against tampered payloads without erroring.
 */
class UpdateEmbedConfigurationAction
{
    /**
     * @param  array<string, mixed>  $validated  Already passed through
     *                                           {@see UpdateEmbedConfigurationRequest::validatedPayload()}.
     */
    public function run(Space $space, array $validated): EmbedConfiguration
    {
        return DB::transaction(function () use ($space, $validated): EmbedConfiguration {
            $config = EmbedConfiguration::updateOrCreate(
                ['space_id' => $space->id],
                [
                    'layout' => $validated['layout'],
                    'dark_mode' => (bool) $validated['dark_mode'],
                    'animation_enabled' => (bool) $validated['animation_enabled'],
                    'show_rating' => (bool) $validated['show_rating'],
                    'background_color' => $this->normalizeBackgroundColor(
                        $validated['background_color'] ?? null,
                    ),
                    'item_limit' => $this->clampItemLimit(
                        (int) $validated['item_limit'],
                    ),
                ],
            );

            $this->syncFieldVisibility($space, $validated['field_visibility'] ?? []);

            return $config;
        });
    }

    /**
     * Uppercase the hex, treat empty string as null. Anything else that
     * slipped past the FormRequest regex (e.g. a future direct caller)
     * becomes null rather than crashing.
     */
    private function normalizeBackgroundColor(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return null;
        }

        return strtoupper($value);
    }

    private function clampItemLimit(int $value): int
    {
        return max(1, min(EmbedConfiguration::MAX_ITEM_LIMIT, $value));
    }

    /**
     * Apply the owner-controlled visibility flags to the Space's live
     * `space_fields` rows. Unknown keys are ignored. Keys that are
     * present in the payload are applied as boolean; keys that are
     * absent leave the existing row value untouched (so a partial
     * submission does not blank out other fields).
     *
     * @param  array<string, bool>  $visibility
     */
    private function syncFieldVisibility(Space $space, array $visibility): void
    {
        if ($visibility === []) {
            return;
        }

        $validKeys = $space->fields()
            ->whereNull('deleted_at')
            ->pluck('field_key')
            ->all();

        $validSet = array_flip($validKeys);

        foreach ($visibility as $key => $value) {
            if (! isset($validSet[$key])) {
                continue;
            }

            SpaceField::query()
                ->where('space_id', $space->id)
                ->where('field_key', $key)
                ->whereNull('deleted_at')
                ->update(['show_in_embed' => (bool) $value]);
        }
    }
}
