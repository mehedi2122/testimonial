<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Plan;
use App\Models\Space;
use App\Models\Testimonial;

/**
 * Public-submission side effects (OpenSpec change: public-testimonial-submission).
 *
 * Group 7: counts the Space's live testimonials and compares against the
 * owner's plan. Returns null when the submitter is allowed to proceed, or
 * an array with `error`, `plan`, `limit` when at/above the cap.
 *
 * Group 8 will extend this class with the atomic create + TestimonialValue
 * writes. Keeping count + persist in one transaction-less action is wrong;
 * Group 8 will introduce a transaction here.
 */
class SubmitTestimonialAction
{
    /**
     * Run the preflight checks that must hold before a testimonial is created.
     *
     * @return array{error: string, plan: Plan, limit: int}|null
     */
    public function checkPlanLimit(Space $space): ?array
    {
        $plan = $space->user->plan();
        $limit = $plan->maxTestimonialsPerSpace();

        $liveCount = Testimonial::query()
            ->where('space_id', $space->id)
            ->whereNull('deleted_at')
            ->count();

        if ($liveCount >= $limit) {
            return [
                'error' => 'limit_reached',
                'plan' => $plan,
                'limit' => $limit,
            ];
        }

        return null;
    }
}
