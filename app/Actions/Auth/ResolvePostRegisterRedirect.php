<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\RegisterResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Post-registration redirect resolver (Agentic Application Shell Refactoring).
 *
 * Newly registered users always have 0 Spaces, so they land on /spaces
 * (the index/create page). Mirrors the "0 spaces" branch of
 * ResolvePostLoginRedirect — same SRP pattern.
 */
class ResolvePostRegisterRedirect implements RegisterResponse
{
    public function toResponse($request): Response
    {
        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended('/spaces');
    }
}
