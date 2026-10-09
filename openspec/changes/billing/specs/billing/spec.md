# billing Specification

## Purpose

Free → Pro upgrade through Stripe Checkout, with Stripe webhooks as the only thing that changes a user's plan (PRD §24–§28).

## ADDED Requirements

### Requirement: `GET /billing` SHALL show both plans and the user's current state

The Billing page SHALL show both plans, the current plan, and subscription state. Authenticated and verified users only. Renders `billing/index` with `plan` (`free|pro`), both plans and their limits, Space usage, and the `default` subscription's state (`status`, `on_grace_period`, `ends_at`, `payment_failed`) or `null`.

#### Scenario: free user

- **GIVEN** a user with no subscription
- **WHEN** `GET /billing`
- **THEN** `plan` is `free` and both plans are listed with their prices and limits

#### Scenario: pro user

- **GIVEN** a user with an `active` `default` subscription
- **WHEN** `GET /billing`
- **THEN** `plan` is `pro` and `subscription.status` is `active`

#### Scenario: guest

- **WHEN** an unauthenticated visitor requests `/billing`
- **THEN** they are redirected to login

### Requirement: `POST /billing/checkout` SHALL start a Stripe Checkout Session for Pro

The endpoint SHALL create a Stripe Checkout Session and send the browser to it. The session is created for the `STRIPE_PRICE_PRO` price under subscription type `default`, with `success_url = /billing?checkout=success` and `cancel_url = /billing?checkout=canceled`. The response sends the browser to Stripe.

#### Scenario: free user upgrades

- **GIVEN** a Free user and a configured price
- **WHEN** `POST /billing/checkout` as an Inertia request
- **THEN** the response is `409` with `X-Inertia-Location` set to the Checkout URL

#### Scenario: already pro

- **GIVEN** a Pro user
- **WHEN** `POST /billing/checkout`
- **THEN** no session is created and the user is redirected back with an error

#### Scenario: price not configured

- **GIVEN** `services.stripe.pro_price` is empty
- **WHEN** `POST /billing/checkout`
- **THEN** no session is created and the user is redirected back with an error

### Requirement: Returning from Checkout SHALL NOT activate Pro

The success redirect SHALL NOT change the user's plan. `?checkout=success` only changes the copy on the Billing page; the plan stays whatever the `subscriptions` table says until a webhook updates it.

#### Scenario: redirect before webhook

- **GIVEN** a Free user returning with `?checkout=success` and no subscription row
- **WHEN** `GET /billing?checkout=success`
- **THEN** `plan` is still `free` and `checkout` is `success`

### Requirement: Stripe webhooks SHALL be the source of truth for the plan

The plan SHALL change only through Stripe webhooks. `POST /stripe/webhook` is CSRF-exempt and verifies the `Stripe-Signature` header whenever `cashier.webhook.secret` is set. `customer.subscription.created/updated/deleted` are applied by Cashier's handlers.

#### Scenario: subscription created

- **GIVEN** a user whose `stripe_id` is `cus_1`
- **WHEN** a signed `customer.subscription.created` event for `cus_1` with status `active` is posted
- **THEN** the user's plan becomes `pro`

#### Scenario: subscription deleted

- **GIVEN** a Pro user
- **WHEN** a `customer.subscription.deleted` event for their subscription is posted
- **THEN** the user's plan becomes `free`

#### Scenario: unsigned request

- **GIVEN** a webhook secret is configured
- **WHEN** a request without a valid `Stripe-Signature` is posted
- **THEN** the response is `403` and nothing changes

### Requirement: `checkout.session.completed` SHALL reconcile a missing subscription on the queue

The webhook SHALL queue reconciliation of a missing subscription. The handler dispatches `SyncCheckoutSubscription`. The job re-reads the subscription from Stripe and applies it through Cashier's created-handler only when the local row is missing.

#### Scenario: subscription row missing

- **GIVEN** a completed subscription-mode session whose subscription has no local row
- **WHEN** the job runs
- **THEN** the subscription is fetched from Stripe and the user becomes Pro

#### Scenario: subscription row present

- **GIVEN** the local row already exists
- **WHEN** the job runs
- **THEN** Stripe is not called

### Requirement: `invoice.payment_failed` SHALL notify the owner once per event

The owner SHALL be emailed at most once per failed-payment event. The handler dispatches `NotifyPaymentFailed`, which mails the owner a link to `/billing`. A replayed event id sends nothing.

#### Scenario: replayed event

- **GIVEN** the same `invoice.payment_failed` event delivered twice
- **WHEN** both jobs run
- **THEN** exactly one notification is sent

## Out of scope (explicit)

- Annual billing, coupons, more than one paid plan (PRD §37).
- A `stripe_events` table (data-model decision #11).
- Proration or in-app plan switching; the Stripe Billing Portal handles cancel and card changes.
