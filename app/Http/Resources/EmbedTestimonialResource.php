<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Actions\Embeds\BuildEmbedWidgetPayloadAction;
use App\Models\Testimonial;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API Resource for the public embed widget and the public Wall of
 * Love page (OpenSpec changes: embed-widget, public-wall-of-love;
 * data-model.md §4.1).
 *
 * The single security boundary for column selection on every public
 * read path. `email` must never enter the projection; this class
 * enforces that by whitelisting the only columns any public surface
 * needs.
 *
 * Why a Resource instead of a manual `->toArray()`: Laravel's
 * JsonResource `toArray($request)` is the canonical place to project
 * an Eloquent model for serialization. Future contributors who reach
 * for `$testimonial->toArray()` get the full model (including email);
 * reaching for `(new EmbedTestimonialResource($t))->toArray($request)`
 * goes through this whitelist. The Resource is the gate.
 *
 * Per data-model.md §4.1: "The embed payload must go through an
 * Eloquent API Resource that whitelists fields, never
 * `Testimonial::find()->toArray()`."
 *
 * The `fields` array is filtered to entries whose `spaceField` is
 * live (`deleted_at IS NULL`) and has `show_in_embed = true`. The
 * eager-load in {@see BuildEmbedWidgetPayloadAction}
 * pre-filters the SQL too, but doing it again here is cheap and
 * defence in depth against a future code path that loads via a
 * different relation.
 *
 * `is_favorite` is PII-safe — it's an internal ordering flag the
 * inbox scope already exposes — and lets the embed iframe and the
 * Wall of Love page render a `Pin` icon for starred rows.
 *
 * @property-read Testimonial $resource
 */
class EmbedTestimonialResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, testimonial: string, rating: int|null, submitted_at: string|null, is_favorite: bool, fields: array<int, array{label: string, value: string, type: string}>}
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'testimonial' => $this->resource->testimonial,
            'rating' => $this->resource->rating,
            'submitted_at' => $this->resource->submitted_at->toIso8601String(),
            'is_favorite' => (bool) $this->resource->is_favorite,
            'fields' => $this->resource->values
                ->map(function ($value): ?array {
                    $field = $value->spaceField;

                    if ($field === null) {
                        return null;
                    }

                    if ($field->deleted_at !== null) {
                        return null;
                    }

                    if (! $field->show_in_embed) {
                        return null;
                    }

                    return [
                        'label' => $field->label,
                        'value' => (string) ($value->value ?? ''),
                        'type' => $field->type->value,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
