<?php

declare(strict_types=1);

namespace App\Http\Controllers\Embed;

use App\Actions\Embeds\BuildEmbedWidgetPayloadAction;
use App\Enums\EmbedLayout;
use App\Support\EmbedSnippet;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Public-facing widget for the embed (OpenSpec change: embed-widget,
 * PRD §23).
 *
 * Two routes:
 *   - frame(publicId): the iframe document. Public, no auth. Returns
 *     a standalone Blade view that the iframe loads.
 *   - loader(): the JS file `public/embed.js`. Static, content-hashed
 *     via the `?v=` query in the snippet (see {@see EmbedSnippet}).
 *
 * The widget is deliberately tiny: a single document per Space,
 * styled inline. No external CSS, no client-side framework, no auth
 * handshake. The owner-side configuration lives on the
 * `embed_configurations` row, populated by the Embed Builder (Group
 * 19).
 *
 * Per-instance overrides via query string let the loader pass
 * `?style=&theme=&limit=&show-rating=&animation=&bg=` to override the saved
 * config. This is forward-looking — today the loader passes them
 * faithfully, but a future change could swap layout/theme at runtime
 * on the host page without re-pasting the snippet.
 */
class EmbedWidgetController extends Controller
{
    /**
     * Render the iframe document.
     *
     * Query string overrides:
     *   - style: 'masonry' | 'carousel' (fallback to saved)
     *   - theme: 'light' | 'dark' (fallback to saved)
     *   - limit: positive integer (fallback to saved, then clamped)
     *   - show-rating: '0' | '1' (fallback to saved)
     *   - animation: '0' | '1' (fallback to saved)
     *   - bg: #RRGGBB (fallback to saved)
     *
     * Unknown values fall back silently — never error to the host.
     */
    public function frame(
        string $publicId,
        Request $request,
        BuildEmbedWidgetPayloadAction $action,
    ): View {
        try {
            $payload = $action->build($publicId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        $payload = $this->applyOverrides($request, $payload);

        return view('embed.frame', $payload);
    }

    /**
     * Serve the static loader script. `Cache-Control` is set to a long
     * max-age so the browser caches aggressively. The script's URL in
     * the snippet is suffixed with `?v={version}` (see EmbedSnippet)
     * so a script change forces a re-fetch.
     */
    public function loader(): BinaryFileResponse
    {
        $path = public_path('embed.js');

        if (! is_file($path)) {
            abort(404);
        }

        return response()
            ->file($path, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'public, max-age=31536000',
            ]);
    }

    /**
     * Apply per-instance query overrides on top of the saved payload.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyOverrides(Request $request, array $payload): array
    {
        $style = $request->query('style');
        if ($style === EmbedLayout::Masonry->value || $style === EmbedLayout::Carousel->value) {
            $payload['layout'] = $style;
        }

        $theme = $request->query('theme');
        if ($theme === 'light' || $theme === 'dark') {
            $payload['dark_mode'] = $theme === 'dark';
        }

        $showRating = $request->query('show-rating');
        if ($showRating === '0' || $showRating === '1') {
            $payload['show_rating'] = $showRating === '1';
        }

        $animation = $request->query('animation');
        if ($animation === '0' || $animation === '1') {
            $payload['animation_enabled'] = $animation === '1';
        }

        $limit = $request->query('limit');
        if (is_string($limit) && ctype_digit($limit)) {
            $payload['testimonials'] = array_slice(
                $payload['testimonials'],
                0,
                max(1, min(50, (int) $limit)),
            );
        }

        $bg = $request->query('bg');
        if (is_string($bg) && preg_match('/^#[0-9A-Fa-f]{6}$/', $bg) === 1) {
            $payload['background_color'] = strtoupper($bg);
        }

        return $payload;
    }
}
