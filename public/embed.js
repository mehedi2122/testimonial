/*!
 * Public embed loader (OpenSpec change: embed-widget).
 *
 * Vanilla JS — no bundler, no framework, ~50 lines.
 *
 * What it does:
 *   1. Reads its own <script src> attribute to derive the origin
 *      (so the host site doesn't need to know the app URL).
 *   2. On DOMContentLoaded, finds every <div data-testimonial-space>.
 *   3. Replaces each with an <iframe src="/embed/{public_id}?...">.
 *      The data-* attributes on the original div are passed through
 *      as query string so the iframe can override the saved config
 *      per instance.
 *
 * The iframe URL is content-hashed via `?v=` (the snippet generator
 * writes a date-based version). The HTTP response carries a long
 * `Cache-Control: public, max-age=31536000` so the script is cached
 * aggressively; the version bump forces a refresh.
 *
 * CSP: this script does not eval, does not inject 3rd-party scripts,
 * and only creates iframes. Safe under `script-src 'self'`.
 */
(function () {
    'use strict';

    var ORIGIN = (function () {
        var src = (document.currentScript && document.currentScript.src) || '';
        try {
            var u = new URL(src);
            return u.origin;
        } catch (e) {
            return '';
        }
    })();

    function val(node, name, fallback) {
        var v = node.getAttribute(name);
        if (v === null || v === '') {
            return fallback;
        }
        return v;
    }

    function buildSrc(node) {
        var publicId = val(node, 'data-testimonial-space', '');
        if (!publicId) {
            return null;
        }

        var qs = new URLSearchParams();
        qs.set('style', val(node, 'data-style', 'masonry'));
        qs.set('theme', val(node, 'data-theme', 'light'));
        qs.set('limit', val(node, 'data-limit', '12'));
        qs.set('show-rating', val(node, 'data-show-rating', '1'));

        var bg = val(node, 'data-bg', '');
        if (bg) {
            qs.set('bg', bg);
        }

        return (
            ORIGIN +
            '/embed/' +
            encodeURIComponent(publicId) +
            '?' +
            qs.toString()
        );
    }

    function mount(node) {
        var src = buildSrc(node);
        if (!src) {
            return;
        }

        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.title = 'Testimonials';
        iframe.loading = 'lazy';
        iframe.setAttribute('scrolling', 'no');
        iframe.style.width = '100%';
        iframe.style.border = '0';
        iframe.style.display = 'block';
        iframe.style.minHeight = '240px';
        iframe.style.height = '480px';

        node.replaceWith(iframe);
    }

    function init() {
        var nodes = document.querySelectorAll('[data-testimonial-space]');
        for (var i = 0; i < nodes.length; i++) {
            mount(nodes[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
