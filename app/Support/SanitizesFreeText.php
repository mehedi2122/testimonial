<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared free-text sanitization for anything a respondent or owner can
 * type into our system (OpenSpec change: testimonial-inbox).
 *
 * `htmlspecialchars()` with ENT_QUOTES + ENT_HTML5 + UTF-8 keeps the
 * stored value readable when echoed back (the wall-of-love renders
 * entities as their original characters) while preventing any script /
 * attribute injection at render time. This is the same rule the public
 * submission endpoint already applies; the trait exists so the inbox's
 * owner-side edit doesn't fork the rule.
 */
trait SanitizesFreeText
{
    /**
     * Strip HTML/script tags from a single free-text input. Null and
     * empty inputs are returned as-is so the schema's NULL semantics
     * are preserved.
     */
    protected function sanitize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return is_string($value) ? $value : null;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
