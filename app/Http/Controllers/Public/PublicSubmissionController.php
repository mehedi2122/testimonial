<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Space;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Skeleton for the public testimonial submission endpoint
 * (OpenSpec change: public-testimonial-submission, group 1).
 *
 * This class only handles routing + placeholder response. Group 2 will
 * add the FormRequest, group 6 the SoftDeletes preflight, group 7 the
 * plan-limit check, and group 8 the atomic create.
 */
class PublicSubmissionController extends Controller
{
    /**
     * Placeholder response for /s/{public_id}/submissions.
     *
     * Returns 201 with the Space name so the route is verifiable end-to-end
     * without the full validation/persistence pipeline yet.
     */
    public function store(string $publicId): JsonResponse
    {
        $space = Space::query()
            ->where('public_id', $publicId)
            ->whereNull('deleted_at')
            ->first();

        if (! $space) {
            return response()->json([
                'error' => 'space_not_found',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
            ],
        ], 201);
    }
}
