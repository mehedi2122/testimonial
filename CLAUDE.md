# CLAUDE.md

Guidance for Claude Code (and humans) working in this repository. The longer, tool-neutral version lives in `AGENTS.md`.

## Project

Testimonial SaaS MVP: owners create **Spaces**, share a public link (`/s/{public_id}`) where customers submit testimonials without an account, moderate them in an **Inbox**, and publish a **Wall of Love** (`/wall/{slug}`) or an embeddable widget (`/embed.js`). Free / Pro plans via Laravel Cashier (Stripe).

Source of truth, in order:

1. `openspec/specs/` — locked-in behaviour (OpenSpec)
2. `docs/data-model.md` — schema and business rules (wins over the PRD where they differ)
3. `docs/PRD.md` — product requirements

## Stack

Laravel 13 · Inertia.js + React 19 (TypeScript) · Tailwind CSS v4 + shadcn/ui · SQLite locally, MySQL in CI · Laravel Cashier · Pest · OpenSpec.

## Commands

```bash
composer dev                      # web server + queue worker + Vite
php artisan migrate --seed        # schema + demo data (maya@brightcopy.co / dev@shiplog.io, password "password")
php artisan test                  # full test suite
vendor/bin/pint --parallel        # PHP formatting
vendor/bin/phpstan analyse --memory-limit=512M
npm run check                     # oxlint + oxfmt
npm run types:check               # TypeScript
npm run build                     # production assets
```

**All of these must pass before every commit.** GitHub Actions runs the same checks on SQLite and on MySQL 8.

## Rules

- **Business logic lives in `app/Actions/`** — one class per business operation. Controllers stay thin: FormRequest → Action → Response. No `app/Services/`.
- **Validation lives in FormRequests** (`app/Http/Requests/`) with custom rules in `app/Rules/`. Never trust frontend-only validation; plan limits and field rules are enforced server-side.
- **Authorize every owner route** with `Gate::authorize(...)` / policies; testimonial routes also check the testimonial belongs to the Space in the URL.
- **Public payloads go through API Resources** (`EmbedTestimonialResource`) — never `->toArray()` a Testimonial; customer emails must never reach a public page.
- **Free text is stored plain** (tags stripped by `SanitizesFreeText`) and escaped on render. Do not entity-encode on write.
- **Spaces are slug-keyed** in owner URLs (`/spaces/{slug}/…`); the public form and embed use the immutable `public_id`.
- **One OpenSpec change per feature**: proposal → spec deltas → tasks; implement group by group; archive when done. Commit messages follow `Group N <what>`.
- **Verify in the browser**, not only with tests: walk the real flow (sign-up, public submission, inbox, embed on an external page) at desktop and mobile widths.
- Schema changes ship with a migration and are applied immediately (`php artisan migrate`) — the app must never run against an un-migrated database.
- No starter-kit leftovers (see `AGENTS.md`).

## Local notes

- `APP_ENV=local` auto-verifies emails (mail goes to the log).
- Billing needs Stripe test keys + `STRIPE_PRICE_PRO` in `.env`; without them the Billing page shows the plans with Upgrade disabled.
