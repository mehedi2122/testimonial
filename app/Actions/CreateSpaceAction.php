<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\Plan;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Space creation (OpenSpec change: space-crud).
 *
 * Single Responsibility: the create flow for a Space belongs to a Space
 * owner. The controller stays thin (validate + dispatch); this class owns
 * the plan-limit preflight, slug uniqueness, and the persistence call.
 *
 * Companion to {@see SubmitTestimonialAction}, which owns the
 * respondent-side creation flow. Two actions, two responsibilities, two
 * bounded contexts.
 */
class CreateSpaceAction
{
    /**
     * Run the preflight checks that must hold before a Space is created.
     * Counts the owner's live (non-soft-deleted) Spaces and compares
     * against their plan cap. Returns null when allowed, or a violation
     * payload otherwise.
     *
     * Shape mirrors {@see SubmitTestimonialAction::checkPlanLimit()} so
     * callers can grow a single envelope handler later if desired.
     *
     * @return array{error: string, plan: Plan, limit: int}|null
     */
    public function checkPlanLimit(User $user): ?array
    {
        $plan = $user->plan();
        $limit = $plan->maxSpaces();

        $liveCount = Space::query()
            ->where('user_id', $user->id)
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
     * Create a Space owned by `$user`. Generates a unique slug from the
     * `name` field, falling back to a short random suffix on collision
     * (soft-deleted rows still reserve their slug).
     *
     * The model's `creating` boot hook generates `public_id`; the
     * `created` boot hook seeds `SpaceField` predefined fields. Both run
     * automatically inside the transaction.
     *
     * @param  array<string, mixed>  $validated  validated form input
     */
    public function create(User $user, array $validated): Space
    {
        return DB::connection()->transaction(function () use ($user, $validated): Space {
            $slug = $this->uniqueSlug((string) $validated['name']);

            $space = Space::query()->create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'slug' => $slug,
                'title' => $validated['title'],
                'subtitle' => $validated['subtitle'] ?? null,
                'ask' => $validated['ask'],
                'theme' => $validated['theme'],
                'rating_enabled' => (bool) $validated['rating_enabled'],
            ]);

            return $space;
        });
    }

    /**
     * Build a slug from the Space name and guarantee uniqueness across
     * both live and soft-deleted rows (the latter intentionally reserve
     * their slug so a "undelete" never silently re-binds an existing
     * /spaces/{slug} URL).
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'space';
        }

        $candidate = $base;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $exists = Space::withTrashed()
                ->where('slug', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $candidate = $base.'-'.Str::lower(Str::random(4));
        }

        throw new RuntimeException(
            "Could not generate a unique slug for Space \"{$name}\" after 5 attempts."
        );
    }
}
