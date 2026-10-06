<?php

declare(strict_types=1);

namespace App\Http\Controllers\Spaces;

use App\Http\Controllers\Controller;
use App\Models\Space;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Placeholder controllers for the /spaces/* tree (Agentic Application Shell
 * Refactoring, session 13-09). Each action is a thin render call — actual
 * dashboard / inbox / embed / settings logic arrives with future OpenSpec
 * changes (one per route).
 *
 * Route model binding uses the Space's `slug` column rather than the primary
 * key, per PRD §3.2 ("slug → /s/{slug}, user-editable, for respondents").
 * Public submissions still key on `public_id` (immutable, embed-targeted).
 */
class SpaceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('spaces/index');
    }

    public function dashboard(Space $space): Response
    {
        return Inertia::render('spaces/dashboard', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }

    public function inbox(Space $space): Response
    {
        return Inertia::render('spaces/inbox', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }

    public function embed(Space $space): Response
    {
        return Inertia::render('spaces/embed', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
                'public_id' => $space->public_id,
            ],
        ]);
    }

    public function settings(Space $space): Response
    {
        return Inertia::render('spaces/settings', [
            'space' => [
                'id' => $space->id,
                'slug' => $space->slug,
                'name' => $space->name,
            ],
        ]);
    }
}
