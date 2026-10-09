<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Public\ShowPublicWallAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public Wall of Love page (OpenSpec change: public-wall-of-love).
 *
 * `GET /wall/{slug}` — public, unauthenticated, no throttle. The
 * owner shares this URL on marketing channels; visitors see a header,
 * hero copy, and a responsive masonry grid of the Space's publicly
 * visible testimonials. The page is part of our app, so it renders
 * inside the standard `app.blade.php` chrome via Inertia.
 *
 * Routing key is `slug` (not `public_id`) because the wall is the
 * owner-shared marketing surface, not the third-party embed key —
 * see data-model §3.2 for the two-identifier rationale.
 *
 * The 404 preflight is delegated to the action via
 * `ModelNotFoundException`. The action resolves `slug` → Space and
 * returns 404-equivalent for unknown / soft-deleted rows.
 */
class PublicWallController extends Controller
{
    /**
     * Render the public Wall of Love page.
     */
    public function show(
        string $slug,
        Request $request,
        ShowPublicWallAction $action,
    ): Response {
        try {
            $space = $action->resolve($slug);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return Inertia::render('public/wall', $action->buildPayload($space));
    }
}
