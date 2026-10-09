# Agent Guidelines

A short, cross-tool orientation for AI coding agents (and humans) working on this repo.

## What this project is

A Testimonial Collection SaaS — owners create **Spaces**, drop a small embed on their site, and the public posts testimonials into a moderated inbox. The wall of love widget then renders the approved set.

Detailed contract:

- `docs/PRD.md` — product requirements
- `docs/data-model.md` — schema, business rules
- `openspec/specs/` — locked-in OpenSpec requirements (the source of truth for behaviour)

## Code organization rules

### Business logic lives in `app/Actions/` classes. Always.

**Single Responsibility:** one Action class per business operation. Controllers stay thin (validate, dispatch, respond). No `app/Services/` directory.

Canonical example: `app/Actions/SubmitTestimonialAction.php`

- One class. Three methods (`checkPlanLimit`, `checkConsentForWallOfLove`, `create`).
- No controller-level business decisions.
- Easy to unit-test without booting the HTTP stack.

Other rules of thumb:

- Controllers only: extract FormRequest, call Action(s), return Response. No `if (...) { DB::... }` inside controllers.
- Actions do not extend a base class — they're plain invokable objects (or classes with a single public entry-point method).
- FormRequests live in `app/Http/Requests/...` and own their own rules, including custom rules from `app/Rules/...`.

### Don't ship Laravel starter-kit baseline.

This repo started as a Laravel React starter-kit. The starter-kit baseline has been removed:

- No `/dashboard` route.
- No "Repository" / "Documentation" links to laravel/react-starter-kit.
- No Laravel SVG logo on the welcome page.
- Sidebar is space-scoped (Dashboard, Inbox, Embed, Settings).
- Branding (page title, logo) reads `APP_NAME` from `config/app.php` via shared Inertia props.

When adding new chrome, follow the existing pattern — no "starter-kit leftovers".

### Spaces are slug-keyed in URLs

`app/Models/Space.php::getRouteKeyName()` returns `slug`. Authenticated in-app URLs are `/spaces/{slug}/...`. The public submission endpoint (`/s/{public_id}/submissions`) uses `public_id` instead — both identifiers are deliberately separate (PRD §3.2).

## Verification

Before every commit:

```bash
npm run check
vendor/bin/pint --parallel
vendor/bin/phpstan analyse --memory-limit=512M
php artisan test
```

All four must pass. The GitHub Actions `tests.yml` workflow runs the same chain and is the final gate.
