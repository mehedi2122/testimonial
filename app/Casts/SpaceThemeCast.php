<?php

declare(strict_types=1);

namespace App\Casts;

use App\Enums\SpaceTheme;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Like the built-in enum cast, but an unknown stored value (an old theme
 * name before its migration has run, a hand-edited row) reads as Minimal
 * instead of throwing — otherwise every page that loads the Space 500s
 * for its owner. Writes stay strict.
 *
 * @implements CastsAttributes<SpaceTheme, SpaceTheme|string>
 */
class SpaceThemeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SpaceTheme
    {
        if ($value === null) {
            return null;
        }

        $theme = SpaceTheme::tryFrom((string) $value);

        if ($theme === null) {
            Log::warning('Unknown space theme; falling back to Minimal.', ['space_id' => $attributes['id'] ?? null, 'theme' => $value]);

            return SpaceTheme::Minimal;
        }

        return $theme;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof SpaceTheme) {
            return $value->value;
        }

        $theme = SpaceTheme::tryFrom((string) $value);

        if ($theme === null) {
            throw new InvalidArgumentException("Unknown space theme [{$value}].");
        }

        return $theme->value;
    }
}
