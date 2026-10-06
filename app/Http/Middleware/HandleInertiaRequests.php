<?php

namespace App\Http\Middleware;

use App\Models\Space;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * Authenticated users get `auth.user.spaces` (id, slug, name) so the
     * SpaceSwitcher can render without a follow-up request. When the route
     * binds a `{space}`, that Space is also shared as `currentSpace` so the
     * sidebar can build space-scoped nav without re-looking-up the param.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $spaces = $user !== null
            ? $user->spaces()->orderBy('id')->get(['id', 'slug', 'name'])
            : new Collection;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    'spaces' => $spaces,
                ],
            ],
            'currentSpace' => $this->resolveCurrentSpace($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * If the current route bound a `{space}`, share it as `currentSpace`.
     * Returns null on public/auth pages so the sidebar collapses to its
     * non-space variant.
     *
     * @return array{id: int, slug: string, name: string}|null
     */
    private function resolveCurrentSpace(Request $request): ?array
    {
        $space = $request->route('space');

        if (! $space instanceof Space) {
            return null;
        }

        return [
            'id' => $space->id,
            'slug' => $space->slug,
            'name' => $space->name,
        ];
    }
}
