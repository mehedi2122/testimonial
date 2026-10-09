<?php
/**
 * Public embed widget — iframe content (OpenSpec change: embed-widget).
 *
 * Standalone HTML document. NO Inertia, NO Vite, NO app shell, NO
 * external CSS. Everything is inline so this works inside a strict
 * cross-origin iframe sandbox. The host site's CSS cannot leak in.
 *
 * Variables passed in by EmbedWidgetController::frame():
 *   - $space_name       string
 *   - $layout           'masonry' | 'carousel'
 *   - $dark_mode        bool
 *   - $background_color string|null  e.g. "#0F172A"
 *   - $animation_enabled bool
 *   - $show_rating      bool
 *   - $testimonials     array<int, array{name, testimonial, rating, fields[]}>
 */
?>
<!DOCTYPE html>
<html lang="en" @if($dark_mode) data-theme="dark" @endif @unless($animation_enabled) class="no-animation" @endunless @if($background_color) style="--bg: {{ $background_color }};" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $space_name }} — Testimonials</title>
    <style>
        :root {
            --bg: #ffffff;
            --fg: #18181b;
            --muted: #71717a;
            --card: #fafafa;
            --card-border: #e4e4e7;
            --star: #facc15;
            --star-empty: #d4d4d8;
            --link: #2563eb;
        }
        html[data-theme="dark"] {
            --bg: #09090b;
            --fg: #fafafa;
            --muted: #a1a1aa;
            --card: #18181b;
            --card-border: #27272a;
            --star: #facc15;
            --star-empty: #3f3f46;
            --link: #60a5fa;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: var(--fg);
            background: var(--bg);
            padding: 16px;
        }
        .attribution {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 12px;
            font-size: 11px;
            color: var(--muted);
        }
        .attribution a { color: var(--muted); text-decoration: none; }
        .attribution a:hover { color: var(--fg); text-decoration: underline; }

        .layout-masonry {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 12px;
        }
        .layout-carousel {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 4px;
        }
        .layout-carousel .card {
            flex: 0 0 320px;
            scroll-snap-align: start;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            opacity: 0;
            transform: translateY(4px);
            animation: fade-in 0.4s ease-out forwards;
        }
        @keyframes fade-in {
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .card { animation: none; opacity: 1; transform: none; }
        }
        .no-animation .card { animation: none; opacity: 1; transform: none; }
        .card-head {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .avatar {
            width: 32px;
            height: 32px;
            border-radius: 999px;
            background: var(--card-border);
            color: var(--fg);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .avatar-photo {
            object-fit: cover;
            padding: 0;
        }
        .name {
            font-weight: 600;
            font-size: 13px;
            color: var(--fg);
        }
        .stars {
            display: inline-flex;
            gap: 1px;
            font-size: 12px;
            line-height: 1;
        }
        .star { color: var(--star); }
        .star-empty { color: var(--star-empty); }
        .body {
            font-size: 13px;
            line-height: 1.55;
            color: var(--fg);
            margin: 0;
            white-space: pre-line;
            word-wrap: break-word;
        }
        .fields {
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-top: 1px solid var(--card-border);
            padding-top: 8px;
            font-size: 12px;
        }
        .field {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .field-label {
            color: var(--muted);
            font-weight: 500;
        }
        .field-value {
            color: var(--fg);
        }
        .empty {
            padding: 32px 16px;
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            border: 1px dashed var(--card-border);
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="attribution">
        <span>{{ $space_name }}</span>
        <a href="{{ config('app.url') }}" target="_blank" rel="noopener noreferrer">powered by {{ config('app.name', 'Testimonials') }}</a>
    </div>

    @if(empty($testimonials))
        <div class="empty">No testimonials yet.</div>
    @else
        <div class="layout-{{ $layout }}">
            @foreach($testimonials as $t)
                <article class="card">
                    <div class="card-head">
                        @if(!empty($t['photo_url']))
                            <img class="avatar avatar-photo" src="{{ $t['photo_url'] }}" alt="" loading="lazy">
                        @else
                            <span class="avatar" aria-hidden="true">
                                {{ mb_strtoupper(mb_substr($t['name'] ?? '?', 0, 1)) }}
                            </span>
                        @endif
                        <span class="name">{{ $t['name'] }}</span>
                    </div>

                    @if($show_rating && !empty($t['rating']))
                        <div class="stars" aria-label="{{ $t['rating'] }} out of 5">
                            @for($i = 1; $i <= 5; $i++)
                                <span class="{{ $i <= $t['rating'] ? 'star' : 'star-empty' }}">★</span>
                            @endfor
                        </div>
                    @endif

                    <p class="body">{{ $t['testimonial'] }}</p>

                    @if(!empty($t['fields']))
                        <dl class="fields">
                            @foreach($t['fields'] as $f)
                                @if(!empty($f['value']))
                                    <div class="field">
                                        <dt class="field-label">{{ $f['label'] }}:</dt>
                                        <dd class="field-value">{{ $f['value'] }}</dd>
                                    </div>
                                @endif
                            @endforeach
                        </dl>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <script>
        // Report the document height to the loader (public/embed.js) so the
        // host page can size the iframe to its content instead of clipping it.
        (function () {
            if (window.parent === window) { return; }
            var last = 0;
            function report() {
                // Measure <body>, not documentElement: scrollHeight never
                // reports less than the iframe's current height, so the
                // frame could grow but never shrink to fit short content.
                var h = Math.ceil(document.body.getBoundingClientRect().height);
                if (h === last) { return; }
                last = h;
                window.parent.postMessage({ type: 'testimonial-embed:resize', height: h }, '*');
            }
            if (window.ResizeObserver) {
                new ResizeObserver(report).observe(document.body);
            }
            window.addEventListener('load', report);
            report();
        })();
    </script>
</body>
</html>