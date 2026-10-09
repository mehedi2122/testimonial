<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\CreateSpaceAction;
use App\Actions\UpdateSpaceSettingsAction;
use App\Models\Space;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Generates a unique Space slug from a name (OpenSpec change:
 * space-settings).
 *
 * Single source of truth for slug generation — used by both
 * {@see CreateSpaceAction} (on create) and
 * {@see UpdateSpaceSettingsAction} (on rename). Soft-deleted
 * rows intentionally reserve their slug so a future undelete never
 * silently re-binds an existing /spaces/{slug} URL.
 *
 * The optional `$ignoreId` lets the update path skip the row it's
 * mutating during the existence check, so a rename to the *same* name
 * keeps the current slug instead of synthesizing a `-xxxx` suffix.
 */
final class UniqueSpaceSlug
{
    /**
     * Build a slug for `$name` and guarantee uniqueness across both live
     * and soft-deleted rows. If `$ignoreId` is provided, that row is
     * excluded from the existence check.
     *
     * @throws RuntimeException when 5 candidates all collide.
     */
    public static function for(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'space';
        }

        $candidate = $base;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $query = Space::withTrashed()->where('slug', $candidate);

            if ($ignoreId !== null) {
                $query->where('id', '!=', $ignoreId);
            }

            if (! $query->exists()) {
                return $candidate;
            }

            $candidate = $base.'-'.Str::lower(Str::random(4));
        }

        throw new RuntimeException(
            "Could not generate a unique slug for Space \"{$name}\" after 5 attempts."
        );
    }
}
