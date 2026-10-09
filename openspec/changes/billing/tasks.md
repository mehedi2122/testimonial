# Tasks: billing

PRD Phase 6. Ships as one change; groups are sequencing guidance.

---

## 1. Config & routing

- [x] 1.1 `services.stripe.pro_price` ← `STRIPE_PRICE_PRO`; add Stripe keys to `.env.example`
- [x] 1.2 `cashier.webhook.events` adds `checkout.session.completed`, `invoice.payment_failed`
- [x] 1.3 `Cashier::ignoreRoutes()` in `AppServiceProvider::register()`; re-register `stripe/payment/{id}` and `stripe/webhook` (same names) in `routes/web.php`
- [x] 1.4 CSRF-exempt `stripe/webhook` in `bootstrap/app.php`
- [x] 1.5 `/billing`, `/billing/checkout`, `/billing/portal` under `auth` + `verified`

## 2. Actions

- [x] 2.1 `Billing\BillingOverviewAction` — plans, usage, subscription state, `checkout` whitelist (`success|canceled`)
- [x] 2.2 `Billing\StartProCheckoutAction` — preflight (already Pro, live failed subscription, price missing) + `createSession()`
- [x] 2.3 `Billing\OpenBillingPortalAction` — preflight (no Stripe customer) + `createSession()`
- [x] 2.4 `Billing\SyncCheckoutSubscriptionAction` — re-read from Stripe only when the local row is missing
- [x] 2.5 `Billing\NotifyPaymentFailedAction` — `Cache::add` dedup per event id (3 days)
- [x] 2.6 `Plan::spaceLimitMessage()` — PRD §25 copy for the Space cap

## 3. Webhooks & queue

- [x] 3.1 `Billing\StripeWebhookController extends` Cashier's controller; handles `checkout.session.completed` and `invoice.payment_failed` by dispatching jobs
- [x] 3.2 `Jobs\SyncCheckoutSubscription`, `Jobs\NotifyPaymentFailed`
- [x] 3.3 `Notifications\PaymentFailedNotification` (mail, links to `/billing`)

## 4. UI

- [x] 4.1 `pages/billing/index.tsx` — two plan cards, current-plan badge, usage, Upgrade / Manage buttons with loading state
- [x] 4.2 `?checkout=success` polls (`usePoll`, 3s, max 20) until the webhook flips the plan; timeout copy afterwards
- [x] 4.3 Alerts: canceled checkout, payment failed (+ portal button), grace period end date, flash errors
- [x] 4.4 Billing in sidebar (both variants) and user menu; Upgrade button on the Free dashboard banner

## 5. Tests

- [x] 5.1 `BillingPageTest` — page props for free / pro / past_due, redirect never activates Pro, checkout + portal hand-off (409 `X-Inertia-Location`), refusals, Stripe outage, PRD Space-limit copy
- [x] 5.2 `StripeWebhookTest` — signed created / updated / deleted / grace period; unsigned and wrong-secret → 403; job dispatch for checkout and payment-failed
- [x] 5.3 `BillingJobsTest` — reconciliation creates missing row / skips Stripe when present / ignores unknown customer; payment-failed mail once per event

## 6. Quality gates

- [x] 6.1 `vendor/bin/pint --parallel`
- [x] 6.2 `vendor/bin/phpstan analyse --memory-limit=512M --no-progress`
- [x] 6.3 `npm run check`
- [x] 6.4 `php artisan test` — 323 passing
- [ ] 6.5 End-to-end against Stripe test mode (needs `STRIPE_PRICE_PRO` and `stripe listen --forward-to localhost:8000/stripe/webhook`) — _owner to run_
