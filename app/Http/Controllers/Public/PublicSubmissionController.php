<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Public\ShowPublicSubmissionAction;
use App\Actions\SubmitTestimonialAction;
use App\Enums\SpaceFieldMode;
use App\Http\Requests\SubmitTestimonialRequest;
use App\Models\SpaceField;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public testimonial submission endpoint (OpenSpec change: public-testimonial-submission).
 *
 * Group 1: route + skeleton returning the resolved Space's name as a 201.
 * Group 2: FormRequest validates the body.
 * Group 6: SoftDeletes preflight (delegated to SubmitTestimonialRequest::space()).
 * Group 7: plan-limit check via SubmitTestimonialAction::checkPlanLimit().
 * Group 8: atomic Testimonial + TestimonialValue[] create via
 *          SubmitTestimonialAction::create().
 * Group 9 (next) will add the is_wall_of_love + consent preflight before
 *           the create call.
 *
 * public-submission-form (later change): added `show()` for the GET
 * `/{public_id}` page the form lives on.
 */
class PublicSubmissionController extends Controller
{
    public function __construct(private readonly SubmitTestimonialAction $action) {}

    /**
     * Render the public submission form (OpenSpec: public-submission-form).
     *
     * Public, unthrottled, no auth. Soft-deleted or unknown public_ids
     * `abort(404)` — same preflight rule as the POST endpoint.
     */
    public function show(
        string $publicId,
        ShowPublicSubmissionAction $showAction,
    ): Response {
        try {
            $space = $showAction->resolve($publicId);
        } catch (ModelNotFoundException) {
            abort(404);
        }

        return Inertia::render('public/submit', [
            'space' => [
                'public_id' => $space->public_id,
                'name' => $space->name,
                'title' => $space->title,
                'subtitle' => $space->subtitle,
                'ask' => $space->ask,
                'theme' => $space->theme->value,
                'rating_enabled' => $space->rating_enabled,
            ],
            'fields' => $space->fields
                ->map(fn (SpaceField $field): array => [
                    'field_key' => $field->field_key,
                    'label' => $field->label,
                    'type' => $field->type->value,
                    'mode' => $field->mode->value,
                    'required' => $field->mode === SpaceFieldMode::Required,
                ])
                ->values()
                ->all(),
        ]);
    }

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

        $payload = $request->validated();

        // Group 9: §15 wall-of-love consent gate. Today the FormRequest
        // already forces consent_given=true, so this is defensive — if
        // the consent rule is ever relaxed, this prevents the bypass.
        $consentFailure = $this->action->checkConsentForWallOfLove($payload);

        if ($consentFailure !== null) {
            return response()->json([
                'error' => $consentFailure['error'],
            ], 422);
        }

        $limitFailure = $this->action->checkPlanLimit($space);

        if ($limitFailure !== null) {
            return response()->json([
                'error' => $limitFailure['error'],
                'plan' => $limitFailure['plan']->value,
                'limit' => $limitFailure['limit'],
            ], 422);
        }

        $testimonial = $this->action->create($space, $payload);

        return response()->json([
            'ok' => true,
            'testimonial' => [
                'id' => $testimonial->id,
                'submitted_at' => $testimonial->submitted_at->toIso8601String(),
            ],
        ], 201);
    }
}
