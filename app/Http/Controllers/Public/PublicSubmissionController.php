<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\SubmitTestimonialAction;
use App\Http\Requests\SubmitTestimonialRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Public testimonial submission endpoint (OpenSpec change: public-testimonial-submission).
 *
 * Group 1: route + skeleton returning the resolved Space's name as a 201.
 * Group 2: FormRequest validates the body.
 * Group 6: SoftDeletes preflight (delegated to SubmitTestimonialRequest::space()).
 * Group 7: plan-limit check via SubmitTestimonialAction::checkPlanLimit().
 * Group 8 (next) will fold the atomic Testimonial + TestimonialValue[] create into
 *           the same action.
 */
class PublicSubmissionController extends Controller
{
    public function __construct(private readonly SubmitTestimonialAction $action) {}

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

        $limitFailure = $this->action->checkPlanLimit($space);

        if ($limitFailure !== null) {
            return response()->json([
                'error' => $limitFailure['error'],
                'plan' => $limitFailure['plan']->value,
                'limit' => $limitFailure['limit'],
            ], 422);
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
