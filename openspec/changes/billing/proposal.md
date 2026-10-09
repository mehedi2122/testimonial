# billing — Proposal

## Why

PRD §24–§28 (Phase 6) commit us to a Free → Pro upgrade through Stripe Checkout, with Stripe webhooks as the source of truth. Today the groundwork exists — Cashier is installed, `User` is `Billable`, the `Plan` enum holds the limits, and both limits (Spaces per user, testimonials per Space) are enforced server-side — but there is no way to pay. A Free user who hits a limit is told to "Upgrade to Pro" with nowhere to go.

## What changes

1. **`GET /billing`** — authenticated Billing page. Shows both plans (Free $0 / 3 Spaces / 100 per Space; Pro $9.99 / 25 Spaces / 1,000 per Space), a current-plan indicator, Space usage, and subscription state (active, cancelling with an end date, payment failed).
2. **`POST /billing/checkout`** — creates a Stripe Checkout Session for the Pro price (`STRIPE_PRICE_PRO`) via `$user->newSubscription('default', $price)->checkout()` and sends the browser to Stripe (`Inertia::location`). Refused when already Pro or when the price isn't configured.
3. **Return from Stripe** — `/billing?checkout=success` shows "Payment received, activating…" and polls until the webhook lands. **The redirect never activates Pro.** `?checkout=canceled` shows a neutral notice.
4. **`POST /billing/portal`** — Stripe Billing Portal for an existing customer (cancel, update card). Cancellation flows back through `customer.subscription.updated/deleted`.
5. **Webhooks** — `App\Http\Controllers\Billing\StripeWebhookController` extends Cashier's controller (so signature verification and the `customer.subscription.*` handlers are unchanged) and adds:
    - `checkout.session.completed` → queued `SyncCheckoutSubscription` job: if the local subscription row is missing (event-order race), re-read it from Stripe and apply it through Cashier's own created-handler. Replay-safe.
    - `invoice.payment_failed` → queued `NotifyPaymentFailed` job: emails the owner a link to the Billing page, at most once per Stripe event id (cache-key dedup, 3 days — Stripe's retry window).
      Cashier's routes are re-registered by us (`Cashier::ignoreRoutes()`) so the webhook resolves to the subclass.
6. **Navigation** — Billing link in the sidebar and user menu; the Free-plan dashboard banner gets an Upgrade button; the Space-limit message uses the PRD §25 wording.

## Assumptions (documented per PRD §38)

- `past_due` / `incomplete` subscriptions are **not** Pro (Cashier's default). The page shows a "payment failed" alert with a portal link.
- A cancelled subscription stays Pro until `ends_at` (grace period, data-model §5.1).
- No `stripe_events` table (data-model decision #11); the payment-failed email is deduped by cache key instead.

## Impact

- **Schema**: none. Cashier tables already migrated.
- **Config**: `services.stripe.pro_price` (`STRIPE_PRICE_PRO`); `cashier.webhook.events` adds `checkout.session.completed`, `invoice.payment_failed`.
- **Routes**: `/billing`, `/billing/checkout`, `/billing/portal` (auth + verified); `/stripe/webhook`, `/stripe/payment/{id}` (re-registered, CSRF-exempt webhook).
- **Queue**: two jobs on the default connection (`database`). `php artisan queue:work` must be running.
- **Dependencies**: none new.
