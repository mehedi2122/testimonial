<?php

declare(strict_types=1);

namespace App\Actions\Embeds;

use App\Enums\EmbedLayout;
use App\Http\Resources\EmbedTestimonialResource;
use App\Models\EmbedConfiguration;
use App\Models\Space;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Read path for the public embed widget (OpenSpec change: embed-widget).
 *
 * Single Responsibility: given a Space's public_id, return a payload
 * array ready to be passed to `view('embed.frame', $payload)`. The
 * controller stays thin; the resource is the column-whitelist gate.
 *
 * Mirrors the rest of the `app/Actions/Embeds/` pattern (Group 19):
 * one public `build` method, all read-side concerns contained here.
 *
 * Defaults (when no `embed_configurations` row exists yet) match the
 * form defaults the Embed Builder uses for an unsaved Space:
 *   - layout = masonry
 *   - dark_mode = false
 *   - animation_enabled = true
 *   - background_color = null
 *   - item_limit = 12
 *   - show_rating = true
 *
 * `item_limit` is clamped to `1..MAX_ITEM_LIMIT`. The FormRequest
 * already caps it on save, but the read path must not trust the
 * column value (e.g. if a future migration sets `item_limit = 200`).
 *
 * Per data-model.md §4.1, the eager-load filters
 * `space_fields.show_in_embed = true AND deleted_at IS NULL` at the
 * SQL level, and the API Resource filters again in PHP — defence in
 * depth.
 */
class BuildEmbedWidgetPayloadAction
{
    /**
     * @return array{
     *   space_name: string,
     *   layout: 'masonry'|'carousel',
     *   dark_mode: bool,
     *   background_color: string|null,
     *   animation_enabled: bool,
     *   show_rating: bool,
     *   testimonials: array<int, array<string, mixed>>
     * }
     *
     * @throws ModelNotFoundException
     */
    public function build(string $publicId): array
    {
        $space = Space::query()
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->with('embedConfiguration')
            ->firstOrFail();

        $config = $space->embedConfiguration;
        $hasConfig = $config !== null;

        $layout = $hasConfig ? $config->layout : EmbedLayout::Masonry;
        $darkMode = $hasConfig ? (bool) $config->dark_mode : false;
        $animationEnabled = $hasConfig ? (bool) $config->animation_enabled : true;
        $showRating = $hasConfig ? (bool) $config->show_rating : true;
        $backgroundColor = $hasConfig ? $config->background_color : null;
        $itemLimit = $this->clampItemLimit(
            $hasConfig ? (int) $config->item_limit : 12,
        );

        $testimonials = $space->testimonials()
            ->publiclyVisible()
            ->with([
                'values.spaceField' => fn ($query) => $query
                    ->where('show_in_embed', true)
                    ->whereNull('deleted_at'),
            ])
            ->limit($itemLimit)
            ->get()
            ->map(fn ($testimonial): array => (new EmbedTestimonialResource($testimonial))->toArray($this->scratchRequest()))
            ->all();

        return [
            'space_name' => $space->name,
            'layout' => $layout->value,
            'dark_mode' => $darkMode,
            'background_color' => $backgroundColor,
            'animation_enabled' => $animationEnabled,
            'show_rating' => $showRating,
            'testimonials' => $testimonials,
        ];
    }

    private function clampItemLimit(int $value): int
    {
        return max(1, min(EmbedConfiguration::MAX_ITEM_LIMIT, $value));
    }

    /**
     * Build a minimal Request instance for the resource to read
     * against. The resource's `toArray($request)` only needs the
     * type-hint; the request body is irrelevant for our projection.
     */
    private function scratchRequest(): Request
    {
        return Request::create('/embed/_internal', 'GET');
    }
}
