<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Plan;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\TestimonialValue;
use Illuminate\Support\Facades\DB;

/**
 * Public-submission side effects (OpenSpec change: public-testimonial-submission).
 *
 * Group 7: counts the Space's live testimonials and compares against the
 * owner's plan. Returns null when allowed, or an array with `error`,
 * `plan`, `limit` when at/above the cap.
 *
 * Group 8: atomically creates a Testimonial + one TestimonialValue per
 * `values` entry, inside a DB transaction. No Space-row lock (rejected
 * per §5.4). The transaction is on the default connection, not on a
 * shared lock — concurrent submits past the cap are caught by the count
 * check on the next request, and the row insert itself is atomic.
 *
 * Group 9: §15 cross-field gate. If a submitter asks to be on the wall
 * (is_wall_of_love=true) but refuses consent (consent_given=false), we
 * reject with `consent_required`. Today the FormRequest forces
 * consent_given=true, so the gate is defensive; if the consent rule is
 * ever relaxed, this gate prevents the bypass.
 *
 * Sanitization (group 10) is folded in here: name, testimonial, and
 * each value are run through htmlspecialchars() before write.
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

    /**
     * Group 9: §15 wall-of-love consent gate.
     *
     * @param  array<string, mixed>  $payload  validated submission body
     * @return array{error: string}|null
     */
    public function checkConsentForWallOfLove(array $payload): ?array
    {
        $wantsWall = (bool) ($payload['is_wall_of_love'] ?? false);
        $consented = (bool) ($payload['consent_given'] ?? false);

        if ($wantsWall && ! $consented) {
            return ['error' => 'consent_required'];
        }

        return null;
    }

    /**
     * Atomically create a Testimonial row and one TestimonialValue row per
     * `values` entry. Returns the persisted Testimonial.
     *
     * The transaction wraps the whole insert batch. No Space-row lock — the
     * plan-limit check above is the guard against over-cap inserts from
     * concurrent submissions (the next request re-counts and rejects).
     *
     * @param  array<string, mixed>  $payload  validated submission body
     */
    public function create(Space $space, array $payload): Testimonial
    {
        return DB::connection()->transaction(function () use ($space, $payload): Testimonial {
            $testimonial = Testimonial::query()->create([
                'space_id' => $space->id,
                'name' => $this->sanitize($payload['name'] ?? ''),
                'email' => $payload['email'] ?? '',
                'testimonial' => $this->sanitize($payload['testimonial'] ?? ''),
                'rating' => $payload['rating'] ?? null,
                'consent_given' => (bool) ($payload['consent_given'] ?? false),
                'is_wall_of_love' => (bool) ($payload['is_wall_of_love'] ?? false),
                'submitted_at' => now(),
            ]);

            $values = is_array($payload['values'] ?? null) ? $payload['values'] : [];
            $fieldKeyToId = $space->fields()->pluck('id', 'field_key')->all();

            foreach ($values as $entry) {
                if (! is_array($entry) || ! isset($entry['field_key'])) {
                    continue;
                }

                $fieldKey = strtolower((string) $entry['field_key']);
                $fieldId = $fieldKeyToId[$fieldKey] ?? null;

                if ($fieldId === null) {
                    continue;
                }

                TestimonialValue::query()->create([
                    'testimonial_id' => $testimonial->id,
                    'space_field_id' => (int) $fieldId,
                    'value' => $this->sanitize((string) ($entry['value'] ?? '')),
                ]);
            }

            return $testimonial;
        });
    }

    /**
     * Strip HTML/script tags from free-text fields. Uses htmlspecialchars so
     * the stored value still displays literally in the wall-of-love (not as
     * raw entities) but cannot be interpreted as markup when rendered.
     *
     * Null/empty inputs are returned as-is so the calling site keeps the
     * schema's NULL semantics where applicable.
     */
    private function sanitize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return is_string($value) ? $value : null;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
