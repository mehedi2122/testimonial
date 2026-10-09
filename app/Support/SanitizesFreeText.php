<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Shared free-text sanitization for anything a respondent or owner can
 * type into our system (PRD §31, data-model §6: "sanitize on write,
 * escape on render").
 *
 * Write side: strip HTML tags, drop control characters, trim. The value
 * is stored as plain text — NOT entity-encoded. Every renderer (React,
 * Blade `{{ }}`) escapes on output, so encoding here as well would show
 * respondents' "Tom & Jerry" as "Tom &amp; Jerry" everywhere.
 */
trait SanitizesFreeText
{
    /**
     * Null and empty inputs are returned as-is so the schema's NULL
     * semantics are preserved.
     */
    protected function sanitize(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return is_string($value) ? $value : null;
        }

        $text = strip_tags((string) $value);

        // Keep tabs and newlines; drop other C0 controls and DEL.
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);

        return trim($text);
    }
}
