<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Space;
use App\Support\UniqueSpaceSlug;
use Illuminate\Support\Facades\DB;

/**
 * Settings edit for an existing Space (OpenSpec change: space-settings).
 *
 * Single Responsibility: the owner-side mutation of `name`, `title`,
 * `subtitle`, `ask`, `theme`, and `rating_enabled` lives here. The
 * controller stays thin (validate + dispatch + redirect).
 *
 * Slug is regenerated only when `name` changed — a no-op rename
 * preserves the current URL. The collision-safe helper accepts an
 * `$ignoreId` so a rename to the *same* name does not synthesize a
 * `-xxxx` suffix spuriously.
 *
 * `public_id` is intentionally never touched (it is set once in
 * `Space::creating` and is the embed snippet's stable identifier). No
 * `touch()` is called — `updated_at` advances naturally through Eloquent
 * on the save below.
 */
class UpdateSpaceSettingsAction
{
    /**
     * Apply the validated settings payload to `$space` and return the
     * reloaded instance (so the controller can read the post-save slug
     * for the redirect).
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(Space $space, array $validated): Space
    {
        return DB::connection()->transaction(function () use ($space, $validated): Space {
            $newName = (string) $validated['name'];
            $nameChanged = $newName !== $space->name;

            $space->name = $newName;
            $space->title = (string) $validated['title'];
            $space->subtitle = $validated['subtitle'] ?? null;
            $space->ask = (string) $validated['ask'];
            $space->theme = $validated['theme'];
            $space->rating_enabled = (bool) $validated['rating_enabled'];

            if ($nameChanged) {
                $space->slug = UniqueSpaceSlug::for($newName, $space->id);
            }

            $space->save();

            return $space->fresh();
        });
    }
}
