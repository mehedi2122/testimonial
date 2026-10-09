<?php

namespace App\Enums;

/**
 * Public-page themes (PRD §10). The look lives in resources/css/app.css
 * under `.space-theme-{value}`; the value is the class suffix.
 */
enum SpaceTheme: string
{
    case Minimal = 'minimal';
    case Modern = 'modern';
    case Clean = 'clean';

    public function label(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return match ($this) {
            self::Minimal => 'Black and white, lots of space',
            self::Modern => 'Dark background, bold accent',
            self::Clean => 'Soft colors, rounded cards',
        };
    }
}
