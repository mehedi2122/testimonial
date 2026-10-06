<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Laravel\Fortify\Contracts\LoginResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-login redirect resolver (Agentic Application Shell Refactoring).
 *
 * Authenticated users with 1+ Spaces land on their first Space's dashboard.
 * Users with 0 Spaces (newly registered, or just-cleaned) land on the
 * /spaces index/create page.
 *
 * The action honours `intended()` so a user who clicked a link to a
 * protected page and then logged in still goes where they meant to.
 */
class ResolvePostLoginRedirect implements LoginResponse
{
    public function toResponse($request): Response
    {
        $user = $request->user();

        $fallback = $this->fallback($user);

        // intended() picks the deepest-matching intended URL the user was
        // bounced away from. If they came from the login screen directly,
        // it falls back to /spaces (or /spaces/{slug}/dashboard when a space
        // exists).
        return redirect()->intended($fallback);
    }

    /**
     * Resolve the fallback landing URL based on the user's Space count.
     */
    private function fallback(mixed $user): string
    {
        if ($user === null) {
            return '/spaces';
        }

        $firstSpace = $user->spaces()->orderBy('id')->first();

        if ($firstSpace !== null) {
            return route('spaces.dashboard', ['space' => $firstSpace->slug], absolute: false);
        }

        return '/spaces';
    }
}
