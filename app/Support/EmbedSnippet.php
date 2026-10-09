<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmbedConfiguration;
use App\Models\Space;

/**
 * Renders the embed snippet the owner pastes onto their external site
 * (OpenSpec change: embed-builder, PRD §22).
 *
 * Pure function: given a Space + its EmbedConfiguration, return the
 * HTML+JS string. Controllers call this and pass the result as the
 * `snippet` Inertia prop; the React side just renders it inside a
 * `<pre>` and wires a Copy button. The snippet never has to be
 * reconstructed client-side.
 *
 * Shape (one logical line per emitted tag for readability):
 *
 *   <div data-testimonial-space="{public_id}"
 *        data-style="{layout}"
 *        data-theme="{light|dark}"
 *        data-bg="{#RRGGBB or absent}"
 *        data-limit="{item_limit}"
 *        data-show-rating="{0|1}"
 *        data-animation="{0|1}"></div>
 *   <script src="{origin}/embed.js?v={mtime}" defer></script>
 *
 * `?v=` is the loader file's mtime: /embed.js is served with a one-year
 * Cache-Control, so the version busts host-page caches when it changes.
 *
 * `data-bg` is omitted entirely when `background_color` is null — no
 * `data-bg=""` left behind for the future `embed-widget` to special-case.
 *
 * The `<script src=".../embed.js">` is a placeholder for the future
 * `embed-widget` OpenSpec change (Group 20). Embedding it here keeps
 * the snippet drop-in for the day that script ships.
 */
class EmbedSnippet
{
    public static function for(Space $space, EmbedConfiguration $config): string
    {
        $attributes = [
            'data-testimonial-space' => $space->public_id,
            'data-style' => $config->layout->value,
            'data-theme' => $config->dark_mode ? 'dark' : 'light',
            'data-limit' => (string) $config->item_limit,
            'data-show-rating' => $config->show_rating ? '1' : '0',
            'data-animation' => $config->animation_enabled ? '1' : '0',
        ];

        if ($config->background_color !== null) {
            $attributes['data-bg'] = $config->background_color;
        }

        $div = '<div '.self::renderAttributes($attributes).'></div>';
        $script = '<script src="'.self::origin().'/embed.js?v='.self::loaderVersion().'" defer></script>';

        return $div."\n".$script;
    }

    /**
     * @param  array<string, string>  $attributes
     */
    private static function renderAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            $parts[] = $name.'="'.htmlspecialchars($value, ENT_QUOTES).'"';
        }

        return implode(' ', $parts);
    }

    private static function loaderVersion(): string
    {
        $mtime = @filemtime(public_path('embed.js'));

        return $mtime === false ? '1' : (string) $mtime;
    }

    /**
     * Public origin for the embed script. Falls back to localhost if
     * APP_URL is unset so tests don't blow up. Trailing slashes are
     * stripped so the concatenation is always `{origin}/embed.js`.
     */
    private static function origin(): string
    {
        $url = (string) config('app.url', 'http://localhost');

        return rtrim($url, '/');
    }
}
