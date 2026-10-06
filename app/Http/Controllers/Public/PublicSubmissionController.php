<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Requests\SubmitTestimonialRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Public testimonial submission endpoint (OpenSpec change: public-testimonial-submission).
 *
 * Group 1: route + skeleton returning the resolved Space's name as a 201.
 * Group 2: FormRequest validates the body; on success the controller still returns 201
 *          with the resolved Space but does not create any rows (group 8 will).
 * Subsequent groups add SoftDeletes preflight, plan-limit check, atomic create,
 * consent_required preflight, sanitization, and the frontend wiring.
 */
class PublicSubmissionController extends Controller
{
    /**
     * Validate the submission body. The FormRequest also resolves the Space and applies
     * the SoftDeletes preflight (group 6), so by the time we reach this method the Space
     * is guaranteed to exist and not be soft-deleted.
     */
    public function store(SubmitTestimonialRequest $request): JsonResponse
    {
        try {
            $space = $request->space();
        } catch (ModelNotFoundException) {
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
