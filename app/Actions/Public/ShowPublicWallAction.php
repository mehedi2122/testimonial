<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Http\Resources\EmbedTestimonialResource;
use App\Models\Space;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;

/**
 * Read path for the public Wall of Love page (OpenSpec change:
 * public-wall-of-love).
 *
 * Single Responsibility: given a Space's `slug`, return a payload
 * array ready to be passed to `Inertia::render('public/wall', $payload)`.
 * The controller stays thin; the resource is the column-whitelist gate.
 *
 * Routing key is `slug` (not `public_id`) because the wall is the
 * owner-shared marketing surface. `data-model.md §3.2` distinguishes
 * the two: `public_id` is the immutable embed key; `slug` is the
 * owner-editable share URL.
 *
 * The read gate is the same `Testimonial::scopePubliclyVisible()`
 * scope the embed uses (favorites first, then `submitted_at` desc,
 * all four §15 conditions applied). The wall has no `item_limit` —
 * it shows the Space's full public set, since the embed is the
 * curated, capped surface and the wall is the canonical mirror.
 *
 * Field visibility reuses `space_fields.show_in_embed` — one flag
 * covers both surfaces per data-model §3.6. The eager-load filters
 * at SQL level, the resource filters again in PHP. Defence in depth.
 */
class ShowPublicWallAction
{
    /**
     * @throws ModelNotFoundException
     */
    public function resolve(string $slug): Space
    {
        return Space::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->firstOrFail();
    }

    /**
     * @return array{
     *   space: array{name: string, title: string, subtitle: string|null, theme: string},
     *   testimonials: array<int, array<string, mixed>>,
     *   count: int
     * }
     */
    public function buildPayload(Space $space): array
    {
        $testimonials = $space->testimonials()
            ->publiclyVisible()
            ->with([
                'values.spaceField' => fn ($query) => $query
                    ->where('show_in_embed', true)
                    ->whereNull('deleted_at'),
            ])
            ->get()
            ->map(fn ($testimonial): array => (new EmbedTestimonialResource($testimonial))->toArray($this->scratchRequest()))
            ->all();

        return [
            'space' => [
                'name' => $space->name,
                'title' => $space->title,
                'subtitle' => $space->subtitle,
                'theme' => $space->theme->value,
            ],
            'testimonials' => $testimonials,
            'count' => count($testimonials),
        ];
    }

    /**
     * Build a minimal Request instance for the resource to read
     * against. The resource's `toArray($request)` only needs the
     * type-hint; the request body is irrelevant for our projection.
     */
    private function scratchRequest(): Request
    {
        return Request::create('/wall/_internal', 'GET');
    }
}
