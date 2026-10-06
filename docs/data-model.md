# Data Model: Testimonial Collection SaaS

**Status**: Agreed, not yet implemented, no code written. This document is the authoritative schema contract. Where it differs from PRD.md §29, this document wins.

**Source**: Companion to PRD.md, informed by product requirements, business rules, and security constraints.

**Stack**: Laravel 12 + Inertia.js + React + MySQL + Laravel Cashier (Stripe).

---

## Table of Contents

1. [Reference Scenario](#1-reference-scenario)
2. [Entity Overview (ERD)](#2-entity-overview-erd)
3. [Tables](#3-tables)
    - [3.1 users](#31-users)
    - [3.2 spaces](#32-spaces)
    - [3.3 space_fields](#33-space_fields)
    - [3.4 testimonials](#34-testimonials)
    - [3.5 testimonial_values](#35-testimonial_values-eav-table)
    - [3.6 embed_configurations](#36-embed_configurations)
    - [3.7 Cashier Tables](#37-cashier-tables)
4. [Key Queries](#4-key-queries)
5. [Plans and Limits](#5-plans-and-limits)
6. [Security and Authorization](#6-security-and-authorization)
7. [Accepted Limitations](#7-accepted-limitations)
8. [Divergences from PRD](#8-divergences-from-prd)
9. [Open Items](#9-open-items)
10. [Future Migration Paths](#10-future-migration-paths)
11. [Decision Log](#11-decision-log)

---

## 1. Reference Scenario

This is the fixture data the seeders will produce and the reference set for queries, UI states, and tests.

### Users, Spaces, and Testimonials

| User                             | Plan | Space                   | Slug            | Testimonials |
| -------------------------------- | ---- | ----------------------- | --------------- | ------------ |
| Maya Sharma (maya@brightcopy.co) | Free | Brightcopy Client Wins  | brightcopy-wins | 6            |
| Maya Sharma                      | Free | Course Launch Feedback  | course-launch   | 3            |
| Dev Okafor (dev@shiplog.io)      | Pro  | Shiplog Product Reviews | shiplog-reviews | 140          |
| Dev Okafor                       | Pro  | Shiplog Beta Testers    | beta-testers    | 12           |

### Why These Counts

- **Priya Raman (priya@nimbus.dev) submitted to both of Dev's Spaces.** This is why "unique respondents" counts distinct emails across Spaces, and why email is a column on `testimonials` rather than an EAV row.

- **Ben Fischer is on Dev's Wall of Love and hidden.** This is why `is_wall_of_love` and `is_hidden` are separate flags — hiding is an override layered on a publish decision, not a replacement.

### Visibility Rule Reference

| #   | Respondent  | consent_given | is_wall_of_love | is_hidden | deleted_at | is_public                      |
| --- | ----------- | ------------- | --------------- | --------- | ---------- | ------------------------------ |
| 41  | Priya Raman | 1             | 1               | 0         | none       | 1                              |
| 42  | Tom Alvarez | 1             | 0               | 0         | none       | 0                              |
| 43  | Sara Nkemi  | 0             | 0               | 0         | none       | 0                              |
| 44  | Ben Fischer | 1             | 1               | 1         | none       | 0 (suppressed, publish intact) |
| 45  | Ana Ruiz    | 1             | 1               | 0         | set        | 0                              |

The rule: `is_public = consent_given AND is_wall_of_love AND NOT is_hidden AND NOT soft_deleted`

---

## 2. Entity Overview (ERD)

**Ownership is a single hop**: `testimonials.space_id → spaces.user_id`. Every policy check is `$space->user_id === $user->id`. There is no account or team layer.

### Relationships

| From         | Label      | To                   | Cardinality  |
| ------------ | ---------- | -------------------- | ------------ |
| users        | owns       | spaces               | 1 to 0..many |
| users        | Cashier    | subscriptions        | 1 to 0..many |
| spaces       | collects   | testimonials         | 1 to 0..many |
| spaces       | defines    | space_fields         | 1 to 0..many |
| spaces       | configures | embed_configurations | 1 to 0..1    |
| testimonials | answers    | testimonial_values   | 1 to 0..many |
| space_fields | typed by   | testimonial_values   | 1 to 0..many |

### Visual ERD

```
┌──────────┐       ┌──────────────────┐       ┌─────────────────────┐
│  users   │──┐    │     spaces       │       │  space_fields       │
│          │  │    │                  │──┐    │                     │
│ id (PK)  │  │    │ id (PK)          │  │    │ id (PK)             │
│ name     │  │    │ user_id (FK)     │  │    │ space_id (FK)       │
│ email    │  │    │ name             │  │    │ field_key           │
│ password │  │    │ slug (UNIQUE)    │  │    │ label               │
│ stripe_id│  │    │ public_id (UQ)   │  │    │ type (enum)         │
└──────────┘  │    │ title            │  │    │ mode (enum)         │
              │    │ theme            │  │    │ sort_order          │
              │    │ rating_enabled   │  │    │ show_in_embed       │
              │    └──────────────────┘  │    └─────────────────────┘
              │                         │              │
              │    ┌──────────────────┐  │              │
              │    │   testimonials   │  │    ┌─────────▼──────────┐
              │    │                  │  │    │testimonial_values  │
              │    │ id (PK)          │  │    │                     │
              │    │ space_id (FK)    │──┘    │ id (PK)             │
              │    │ name             │       │ testimonial_id (FK) │
              │    │ email            │       │ space_field_id (FK) │
              │    │ testimonial      │       │ value (TEXT)        │
              │    │ rating           │       └─────────────────────┘
              │    │ consent_given    │
              │    │ is_favorite      │       ┌─────────────────────┐
              │    │ is_wall_of_love  │       │embed_configurations│
              │    │ is_hidden        │       │                     │
              │    │ is_public (GEN)  │       │ id (PK)             │
              │    │ submitted_at     │       │ space_id (FK, UQ)   │
              └────└──────────────────┘       │ layout              │
                                                │ dark_mode           │
                                                │ item_limit          │
                                                └─────────────────────┘

┌──────────────────┐
│  subscriptions   │  ← Standard, source of truth for plan
│ (Cashier++)      │
└──────────────────┘
```

### New Tables Summary

**5 new tables** for application logic: `spaces`, `space_fields`, `testimonials`, `testimonial_values`, `embed_configurations`.

**2 tables** come from the starter kit and Cashier: `users`, `subscriptions`, `subscription_items`.

---

## 3. Tables

### 3.1 users

Standard Laravel auth plus Cashier columns. The `User` model is the Billable model.

**No `plan` column** — plan is derived from `subscribed('default')` (see §5.1).
**No `timezone` column** — all dates are UTC (see §7.1).

| Column                  | Type              | Notes            |
| ----------------------- | ----------------- | ---------------- |
| id                      | bigint PK         |                  |
| name                    | varchar(120)      |                  |
| email                   | varchar(180)      | unique           |
| email_verified_at       | timestamp null    | per PRD §4       |
| password                | varchar(255)      |                  |
| remember_token          | varchar(100) null |                  |
| `stripe_id`             | varchar(255) null | Cashier, indexed |
| `pm_type`               | varchar(255) null | Cashier          |
| `pm_last_four`          | varchar(4) null   | Cashier          |
| `trial_ends_at`         | timestamp null    | Cashier          |
| created_at / updated_at | timestamp         |                  |

**Indexes**: `unique(email)`, `index(stripe_id)`

---

### 3.2 spaces

An individual testimonial collection page owned by a User.

**Indexes**: `unique(slug)`, `unique(public_id)`, `index(user_id, deleted_at)`

**No `field_configuration` column** — replaced by the `space_fields` table (see §3.3).

| Column                  | Type                 | Notes                                             |
| ----------------------- | -------------------- | ------------------------------------------------- |
| id                      | bigint PK            |                                                   |
| user_id                 | bigint FK → users.id | cascade on delete                                 |
| name                    | varchar(120)         | internal identification (owner-only)              |
| slug                    | varchar(60)          | globally unique                                   |
| public_id               | char(12)             | unique, non-sequential, immutable                 |
| title                   | varchar(160)         | public-facing                                     |
| subtitle                | varchar(255) null    | public-facing                                     |
| ask                     | text                 | the testimonial request                           |
| theme                   | enum                 | `minimal_light` \| `minimal_dark` \| `soft_color` |
| rating_enabled          | boolean              | default `true`                                    |
| created_at / updated_at | timestamp            |                                                   |
| deleted_at              | timestamp null       | soft delete                                       |

#### Two Public Identifiers, Deliberately

They cannot be one column. If embeds used slug, renaming `brightcopy-wins` to `client-wins` would silently break every embed already live on Maya's clients' sites.

| Column    | Job                                                      | Audience       | Mutable?           |
| --------- | -------------------------------------------------------- | -------------- | ------------------ |
| slug      | Shareable collection URL: `/s/brightcopy-wins`           | respondents    | yes, user-editable |
| public_id | Embed key: `<div data-testimonial-space="9xKf2mQr7bZa">` | external sites | never              |

#### Slug Rules

- **Globally unique**, enforced by a database unique index (application-level checks race).
- **Route is `/s/{slug}`**, not the root. At the root, slugs would collide with every app route (`/dashboard`, `/billing`) and need an ever-growing reserved list.
- **Reserved anyway, defensively**: `s`, `api`, `admin`, `login`, `register`, `dashboard`, `billing`, `embed`, `assets`, `up`.
- **On collision**, the create form suggests a suffixed slug (e.g., `course-launch-2`).

#### Soft Delete

Soft delete is added (divergence 8.6 from PRD). Without it, Maya at 3/3 on Free could only fix a slug typo by paying $9.99/month.

---

### 3.3 space_fields

Holds both predefined and user-defined respondent fields, uniformly. **Custom fields are supported** (divergence 8.2 — PRD §16 marks custom fields out of scope; this decision supersedes).

**Indexes**: `unique(space_id, field_key)`, `index(space_id, sort_order)`

| Column                  | Type                  | Notes                                             |
| ----------------------- | --------------------- | ------------------------------------------------- |
| id                      | bigint PK             |                                                   |
| space_id                | bigint FK → spaces.id | cascade on delete                                 |
| field_key               | varchar(40)           | machine key, e.g., `company_name`, `job_title`    |
| label                   | varchar(80)           | shown on the public form                          |
| type                    | enum                  | `text` \| `url` \| `email` \| `image` \| `number` |
| mode                    | enum                  | `off` \| `optional` \| `required`, default `off`  |
| sort_order              | smallint unsigned     | default 0                                         |
| show_in_embed           | boolean               | default `true`                                    |
| created_at / updated_at | timestamp             |                                                   |
| deleted_at              | timestamp null        | soft delete (see §9.1)                            |

#### Why `mode` Is One Enum, Not `enabled` + `required`

Two booleans allow four states, and one is nonsense:

| enabled | required | Meaning                                         |
| ------- | -------- | ----------------------------------------------- |
| 1       | 1        | shown, must fill ✅                             |
| 1       | 0        | shown, optional ✅                              |
| 0       | 0        | hidden ✅                                       |
| 0       | 1        | hidden but mandatory — an unsubmittable form 💥 |

One enum makes the fourth row unrepresentable rather than merely discouraged.

#### Seeding

Every new Space gets three predefined rows at `mode = 'off'`:

| field_key     | label              | type  | sort_order |
| ------------- | ------------------ | ----- | ---------- |
| company_name  | Company name       | text  | 10         |
| social_url    | Social profile URL | url   | 20         |
| profile_photo | Profile photo      | image | 30         |

Custom fields append with `sort_order` above 30.

#### Reserved field_key Values

A custom key must not collide with a `testimonials` column, or the value would have two homes:

`name`, `email`, `testimonial`, `rating`, `consent_given`, `is_favorite`, `is_wall_of_love`, `is_hidden`, `is_public`, `submitted_at`

#### Why `name` and `email` Are NOT Rows Here

They are columns on `testimonials`. See §3.4 and the decision log #5.

---

### 3.4 testimonials

A single piece of feedback submitted by a respondent through a Space's public form.

**Indexes**:

- `idx_public` (`(space_id, is_public, is_favorite, submitted_at)`) — embed reads, index-ordered, no filesort
- `idx_space_live` (`(space_id, deleted_at, submitted_at)`) — limit count, collection graph, inbox
- `idx_respondents` (`(space_id, deleted_at, email)`) — `COUNT(DISTINCT email)`

| Column                  | Type                     | Notes                                        |
| ----------------------- | ------------------------ | -------------------------------------------- |
| id                      | bigint PK                |                                              |
| space_id                | bigint FK → spaces.id    | cascade on delete                            |
| name                    | varchar(120)             | always required (PRD §13)                    |
| email                   | varchar(180)             | always required, never public (PRD §13)      |
| testimonial             | text                     | always required                              |
| rating                  | tinyint unsigned null    | 1..5, null when Space disables rating        |
| consent_given           | boolean                  | default `false`                              |
| is_favorite             | boolean                  | default `false`, ordering only               |
| is_wall_of_love         | boolean                  | default `false`                              |
| is_hidden               | boolean                  | default `false`                              |
| is_public               | boolean STORED generated | see below                                    |
| submitted_at            | timestamp                | domain timestamp, distinct from `created_at` |
| created_at / updated_at | timestamp                |                                              |
| deleted_at              | timestamp null           | soft delete (PRD §15)                        |

#### The `is_public` Generated Column

The PRD §15 four-condition rule runs on the embed endpoint, live preview, Wall-of-Love empty state, and dashboard. Hand-written at four places, one would eventually be wrong (Ben appearing on a customer's site after being hidden). So the rule lives in the schema:

```sql
is_public TINYINT(1) AS (
  CASE WHEN consent_given = 1
        AND is_wall_of_love = 1
        AND is_hidden = 0
        AND deleted_at IS NULL
       THEN 1 ELSE 0 END
) STORED
```

In Laravel:

```php
$table->boolean('is_public')->storedAs(
    'case when consent_given = 1 and is_wall_of_love = 1
          and is_hidden = 0 and deleted_at is null
          then 1 else 0 end'
);
```

**Verify before building**: confirm your MySQL server accepts `deleted_at IS NULL` inside a STORED expression.

`scopePubliclyVisible()` stays as the app-facing API and resolves to `where('is_public', true)`.

**Cost accepted**: recomputed on every insert/update (cheap on a read-heavy table). Changing the §15 rule later needs a deliberate, reviewed migration.

#### Why `is_wall_of_love` and `is_hidden` Stay Separate

Hide is an override layered on a publish decision, not a replacement. With one visibility enum, hiding Ben overwrites `published`, and unhiding has nowhere to return to (he lands silently in inbox, demoted). Two booleans restore him automatically.

**Application rule**: block `is_wall_of_love = 1` while `consent_given = 0`. The generated column already makes Sara unpublishable, but a toggle that can never take effect is a UI that lies.

#### Why `name` and `email` Are Columns, Not `space_fields` Rows

1. **They are always enabled and required.** A field that cannot vary is part of what a testimonial _is_, not configuration.
2. **It makes the email guarantee structural, not procedural.** `testimonial_values` never contains an email, so an embed that dumps every value still cannot leak one.
3. **It keeps "unique respondents" an indexed aggregate**:

```sql
-- Actual model (indexed on email)
SELECT COUNT(DISTINCT email) FROM testimonials
WHERE space_id IN (3, 4) AND deleted_at IS NULL;

-- Pure EAV would have required this (unindexable on a shared TEXT column)
SELECT COUNT(DISTINCT tv.value)
FROM testimonial_values tv
JOIN space_fields sf ON sf.id = tv.space_field_id
JOIN testimonials t  ON t.id = tv.testimonial_id
WHERE sf.type = 'email' AND t.space_id IN (3, 4) AND t.deleted_at IS NULL;
```

---

### 3.5 testimonial_values (EAV Table)

Answers to `space_fields`. **Never contains an email address** (structural guarantee).

**Indexes**: `unique(testimonial_id, space_field_id)`, `index(space_field_id)`

| Column                  | Type                        | Notes                                  |
| ----------------------- | --------------------------- | -------------------------------------- |
| id                      | bigint PK                   |                                        |
| testimonial_id          | bigint FK → testimonials.id | cascade on delete                      |
| space_field_id          | bigint FK → space_fields.id | restrict (§9.1)                        |
| value                   | text null                   | for `type = 'image'`, the storage path |
| created_at / updated_at | timestamp                   |                                        |

#### Example: Priya's Submission to Shiplog Product Reviews

Her name, email, testimonial text, and rating are columns on `testimonials`, not here:

| testimonial_id | space_field_id | field              | value                    |
| -------------- | -------------- | ------------------ | ------------------------ |
| 41             | 9              | company_name       | Nimbus Dev               |
| 41             | 10             | social_url         | https://nimbus.dev/priya |
| 41             | 12             | job_title (custom) | VP Engineering           |

---

### 3.6 embed_configurations

**Exactly 0 or 1 per Space**. The row is optional: with no saved row, code defaults apply, so the Embed page renders before it is ever saved.

**No `display_options` JSON column** (divergence 8.4).

| Column                  | Type                  | Notes                                      |
| ----------------------- | --------------------- | ------------------------------------------ |
| id                      | bigint PK             |                                            |
| space_id                | bigint FK → spaces.id | **unique**, cascade on delete              |
| layout                  | enum                  | `masonry` \| `carousel`, default `masonry` |
| dark_mode               | boolean               | default `false`                            |
| animation_enabled       | boolean               | default `true`                             |
| background_color        | char(7) null          | `#RRGGBB`                                  |
| item_limit              | smallint unsigned     | default 12, capped at 50                   |
| show_rating             | boolean               | default `true`                             |
| created_at / updated_at | timestamp             |                                            |

#### Where Visibility Toggles Live

PRD §20's three hardcoded toggles break with custom fields (a "Job Title" answer would be collected but permanently unpublishable). Each toggle sits next to the thing it governs, so there is no whitelist to forget:

| What                           | Storage                    | Visibility toggle                  |
| ------------------------------ | -------------------------- | ---------------------------------- |
| Respondent name                | `testimonials.name`        | always shown                       |
| Testimonial text               | `testimonials.testimonial` | always shown                       |
| Email                          | `testimonials.email`       | never shown, not addressable       |
| Rating                         | `testimonials.rating`      | `embed_configurations.show_rating` |
| Company, social, photo, custom | `space_fields` rows        | `space_fields.show_in_embed`       |

#### Item Limit

`item_limit` is capped because an embed of 1,000 testimonials would hang someone's homepage.

#### One Embed Per Space

Fixed by the snippet format: `<div data-testimonial-space="SPACE_PUBLIC_ID">` addresses a Space, not an embed.

---

### 3.7 Cashier Tables

`subscriptions` and `subscription_items` are standard and unmodified. `subscriptions` is the local source of truth for subscription state. **Nothing denormalizes it.**

```
subscriptions
  id
  user_id
  type
  stripe_id (unique)
  stripe_status
  stripe_price
  quantity
  trial_ends_at
  ends_at          ← grace period: cancelled sub with future ends_at = still active
  created_at
  updated_at
```

---

## 4. Key Queries

### 4.1 Embed Read (Hottest Path)

```sql
-- 1. Space lookup by public_id. SoftDeletes excludes deleted Spaces,
--    so a deleted Space yields an empty state rather than a 500 on a customer's live site.
SELECT id FROM spaces WHERE public_id = ? AND deleted_at IS NULL;

-- 2. Testimonials, index-ordered off idx_public, no filesort.
--    Explicit column list. `email` must never enter the projection.
SELECT id, name, testimonial, rating, submitted_at
FROM testimonials
WHERE space_id = ? AND is_public = 1
ORDER BY is_favorite DESC, submitted_at DESC
LIMIT ?;

-- 3. Visible field values only.
SELECT tv.testimonial_id, sf.field_key, sf.type, tv.value
FROM testimonial_values tv
JOIN space_fields sf ON sf.id = tv.space_field_id
WHERE tv.testimonial_id IN (?) AND sf.show_in_embed = 1 AND sf.deleted_at IS NULL;
```

**The generated column protects row selection, not column selection.** The embed payload must go through an Eloquent API Resource that whitelists fields, never `Testimonial::find()->toArray()`.

---

### 4.2 Dashboard Stats

```sql
-- Total Spaces
SELECT COUNT(*) FROM spaces WHERE user_id = ? AND deleted_at IS NULL;

-- Total testimonials
SELECT COUNT(*) FROM testimonials
WHERE space_id IN (SELECT id FROM spaces WHERE user_id = ? AND deleted_at IS NULL)
  AND deleted_at IS NULL;

-- Unique respondents (Priya counts once across Dev's two Spaces)
SELECT COUNT(DISTINCT email) FROM testimonials
WHERE space_id IN (...) AND deleted_at IS NULL;

-- Collection graph (7 / 30 / 90 days), grouped by UTC day
SELECT DATE(submitted_at) AS d, COUNT(*) AS c
FROM testimonials
WHERE space_id IN (...) AND deleted_at IS NULL AND submitted_at >= ?
GROUP BY d;
```

**Zero-count days don't appear in GROUP BY output**, so they must be filled in application code from the requested range.

---

### 4.3 Limit Check (Every Public Submission)

```php
$count = Testimonial::where('space_id', $space->id)->count(); // SoftDeletes → deleted_at IS NULL
if ($count >= $plan->maxTestimonialsPerSpace()) {
    // 422 + upgrade message. Never silently discard. (PRD §25)
}
```

---

## 5. Plans and Limits

### 5.1 Plan Resolution

**Limits live in a `Plan` enum in code. No plans table, no `users.plan` column.**

```php
enum Plan
{
    case Free;
    case Pro;

    public function maxSpaces(): int
    {
        return match ($this) { Plan::Free => 3, Plan::Pro => 25 };
    }

    public function maxTestimonialsPerSpace(): int
    {
        return match ($this) { Plan::Free => 100, Plan::Pro => 1000 };
    }
}

$plan = $user->subscribed('default') ? Plan::Pro : Plan::Free;
```

#### Why No Plans Table

It would hold two rows nobody can edit (no admin UI in scope), and limits are entangled with enforcement logic, banner copy, and the Stripe price ID.

#### Why No `users.plan` Column

It would mirror Cashier's `subscriptions`, which already mirrors Stripe. If they disagree (`plan = 'pro'` but `stripe_status = 'canceled'`), every gating answer is a bug. Webhooks arrive out of order and sometimes fail.

#### Grace Periods Are Free

`subscribed('default')` treats a cancelled subscription with a future `ends_at` as active (Dev cancels on 17 July, stays Pro until the period closes).

---

### 5.2 Downgrade Behaviour (PRD §25)

Limits are checked per Space at submission time. Dev cancels; he has 2 Spaces (under the Free limit of 3), so he keeps both. **Nothing is deleted. Existing data stays accessible.**

### 5.4 The Boundary Race Is Accepted

At 99/100, two simultaneous submissions both pass and the Space lands at 101. The cap is a monetization nudge, not an invariant; PRD §25 says "never silently discard submissions", so accepting a 101st is the right side to err on.

**Rejected**: `SELECT … FOR UPDATE` on the Space row (lock contention on the hot public endpoint, held across a multi-table write of testimonial + EAV rows + photo).

**Rejected**: a `spaces.testimonials_count` counter cache (drift across create / soft-delete / restore / force-delete, needs a reconciliation command, and `COUNT(*)` on an index over at most 1,000 rows is sub-millisecond).

If counting ever becomes expensive, the counter cache is additive.

---

## 6. Security and Authorization (PRD §31)

Choosing columns over pure EAV for name/email turned §31 from a policy every query must honour into a property the schema enforces. Even an embed that eager-loads `$testimonial->values` and dumps everything cannot leak an address. The remaining exposure is column selection on `testimonials`, covered by the API Resource whitelist.

### Authorization Summary

| Requirement                              | How it is met                                                                                   |
| ---------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Users access only their own Spaces       | `$space->user_id === $user->id`; single-hop policies                                            |
| Public IDs non-sequential, hard to guess | `public_id char(12)`, random, unique, immutable                                                 |
| Email never exposed via embeds           | **Structural**: email is a column, `testimonial_values` cannot hold one; API Resource whitelist |
| Escape/sanitize testimonial content      | Application layer: sanitize on write, escape on render                                          |
| Restrict photo type and size             | `space_fields.type = 'image'`; JPG/PNG/WebP + size cap validated on upload                      |
| Rate limits on auth + public submission  | Route middleware                                                                                |
| Stripe webhook signatures verified       | Cashier                                                                                         |
| All dates in UTC                         | All timestamps UTC (§7.1)                                                                       |

---

## 7. Accepted Limitations

_"Known, decided, and recorded so they are not rediscovered as bugs."_

### 7.1 Graph Buckets by UTC Day

**No `users.timezone`.** Dev in Lagos (UTC+1): Priya submits at 23:30 UTC on 16 July (00:30 on the 17th for Dev), so the graph bars it on the 16th and "collected today" reads 0 while her notification email sits in his inbox.

**Accepted**: it reads PRD §31 literally and keeps the query trivial. Retrofit is not free: adding `users.timezone` later rebases every historical bucket on the day it ships. The upgrade path is a daily rollup table (`space_id, local_date, count`).

**Exposure by timezone offset**:

| User's offset       | Submissions on the wrong day                                      |
| ------------------- | ----------------------------------------------------------------- |
| UTC+1 (Lagos)       | 23:00 to 00:00 UTC daily                                          |
| UTC-7 (Los Angeles) | every submission 17:00 to 24:00 local, roughly a third of the day |

---

### 7.2 Webhook Idempotency Is by Discipline, Not Construction

**No `stripe_events` table and no dedup layer.**

Stripe retries with backoff for up to three days. Every handler we own must be hand-written replay-safe, and nothing signals when one isn't. Cashier's handlers being naturally idempotent is why this looks fine in development.

**Exposure**: a duplicate `invoice.payment_failed` sends Dev the same failure email three times.

**Stripe doesn't guarantee order** (`subscription.updated` can land before `created`). Cashier's `updateOrCreate` mostly absorbs this. The belt-and-braces fix (treat the webhook as a signal and re-read state from the Stripe API) is noted, not built.

| Event                           | Handler                  | Replay-safe?                |
| ------------------------------- | ------------------------ | --------------------------- |
| `customer.subscription.created` | Cashier `updateOrCreate` | ✅ by construction          |
| `customer.subscription.updated` | Cashier `updateOrCreate` | ✅ by construction          |
| `customer.subscription.deleted` | Cashier                  | ✅ by construction          |
| `checkout.session.completed`    | ours                     | ⚠️ only if written that way |
| `invoice.paid`                  | ours                     | ⚠️ only if written that way |
| `invoice.payment_failed`        | ours                     | ⚠️ only if written that way |

**If it bites**: a `stripe_events` table with `unique(stripe_event_id)` and INSERT-first (duplicate key = already seen) is additive and race-free.

---

### 7.3 No Local Webhook Log

Stripe's Dashboard (30 days of events, full payloads, delivery attempts, status codes) is the log of record. It records what Stripe did, not what our handler did (for example "delivered, 200" while Dev paid and stayed on Free). It cannot show which user was touched or whether a queued job failed seconds later. PRD §28 "Logged" is met in the practical sense, not in the sense of correlating events to application outcomes.

---

### 7.4 Consent Records the Fact, Not the Time or Scope

`consent_given` is one bit, but it is a publicity permission (§31): name, face, employer and words on a website and on social media. If the consent wording is ever changed (for example "and in paid advertising"), nothing distinguishes older consents. Asked "what did Priya agree to, and when?", the answer is 1. `consented_at` / `consent_text_version` added later cannot be backfilled, because what Priya saw was never captured.

**Accepted**: §15's gating requirement is met; only the audit trail is absent.

---

### 7.5 Soft-Deleted Spaces Hold Their Slug Forever

A soft-deleted Space keeps its row, so its slug stays claimed. Maya cannot recreate `course-launch` after deleting it, and neither can anyone else.

**Do not "fix" with `unique(slug, deleted_at)`**. MySQL treats NULLs as distinct in unique indexes, so two live Spaces (both `deleted_at IS NULL`) could share a slug. That is worse than the leak.

**Rejected**: slug mangling on delete (`course-launch__deleted_7`) corrupts the slug on restore; a purge job needs a retention policy. Both are additive if slug exhaustion ever bites.

---

### 7.6 Testimonial Limit May Overshoot by One

See §5.4. **Deliberate.**

---

## 8. Divergences from PRD

Items 8.1 to 8.4 are one decision: **custom fields are in scope**, and the flexibility must run the full chain: definition (`space_fields`), storage (`testimonial_values`), display (`show_in_embed`). Otherwise it does not exist at all.

| #   | PRD says                                                             | This model does                                                   | Why                                                                                              |
| --- | -------------------------------------------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ |
| 8.1 | §29: `spaces.field_configuration` (JSON)                             | `space_fields` table                                              | Field set is open; JSON can't describe user-defined fields                                       |
| 8.2 | §37: "Custom respondent fields" out of scope                         | In scope                                                          | Decided during design; §37 is stale on this line                                                 |
| 8.3 | §29: `testimonials.company_name`, `social_url`, `profile_photo_path` | Moved to `testimonial_values`                                     | Fixed columns can't hold answers to user-defined fields                                          |
| 8.4 | §29: `embed_configurations.display_options` (JSON)                   | `space_fields.show_in_embed` + `embed_configurations.show_rating` | Three hardcoded keys can't express visibility for custom fields                                  |
| 8.5 | §29: no `is_public`                                                  | `is_public` STORED generated column                               | §15's rule enforced in the schema rather than restated at four call sites                        |
| 8.6 | §7: Space deletion unspecified                                       | Soft delete added                                                 | Without it, Maya at 3/3 on Free can only fix a typo by paying                                    |
| 8.7 | §29: `rating_enabled` alongside JSON field config                    | `rating_enabled` retained as a column                             | Rating is a `testimonials` column, not a `space_fields` row, so the toggle belongs with the data |

---

## 9. Open Items (Resolve Before or During Implementation)

### 9.1 Deleting a `space_field`

Dev deletes his custom "Job Title" field. What happens to Priya's answer, "VP Engineering"?

- `space_fields.deleted_at` exists and `testimonial_values.space_field_id` is `restrict`, so a hard delete is impossible and answers survive.
- **Undecided**: should a soft-deleted field's values stay visible in the inbox? Should `show_in_embed` be forced false? What happens if a field with the same `field_key` is recreated (`unique(space_id, field_key)` will collide with the soft-deleted row, same class of problem as §7.5)?

### 9.2 Purge Policy for Soft-Deleted Rows

Soft-deleted testimonials, Spaces, and `space_fields` accumulate indefinitely. PRD §25's "existing data must not be deleted automatically" concerns subscription expiry, not user-initiated deletion, so a purge is not forbidden, merely unspecified. **No retention policy exists.**

### 9.3 Profile Photo Storage

`space_fields.type = 'image'` stores a path in `testimonial_values.value` (a TEXT column). **Undecided**: disk, path convention, orphaned-file cleanup on testimonial delete, and whether the JPG/PNG/WebP + size restriction is validated from `type` alone or needs per-field config.

### 9.4 Custom Field Validation Rules

`type` implies a base rule (`url → url`, `number → numeric`), but max lengths, number ranges, and whether `type = 'email'` is even permitted for a custom field (given §31's email stance) are unspecified.

---

## 10. Future Migration Paths

Deliberately deferred, all additive:

| Want                      | Path                                                                                                                                                         | Cost                                                                       |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------- |
| Team accounts             | Create `accounts`, backfill one per user, add `spaces.account_id`, move Cashier columns off `users`                                                          | Moderate: touches live subscription data, so cheaper before revenue scales |
| Per-user slug namespaces  | Add `users.handle`, route `/{handle}/{slug}`, relax `unique(slug)` to `unique(user_id, slug)`                                                                | Moderate: breaks existing public URLs                                      |
| Timezone-correct graphs   | Add `users.timezone` + a daily rollup table (`space_id, local_date, count`)                                                                                  | Rebases historical buckets on ship day                                     |
| Webhook idempotency       | `stripe_events` table, `unique(stripe_event_id)`, INSERT-first                                                                                               | Low, additive                                                              |
| Strict limit enforcement  | `spaces.testimonials_count` + atomic conditional UPDATE                                                                                                      | Low, additive                                                              |
| Multiple embeds per Space | `embed_field_visibility` pivot and a new snippet format (the current `data-testimonial-space` attribute addresses a Space, so the snippet must change first) | Moderate: the blocker is the URL scheme, not the schema                    |
| Multiple paid plans       | `plans` table + admin UI (build both together, or not at all)                                                                                                | Moderate                                                                   |
| Consent audit trail       | `consented_at` + `consent_text_version`                                                                                                                      | **Cannot be backfilled** (see §7.4)                                        |

---

## 11. Decision Log

The full set of agreed design decisions, with rejected alternatives:

| #   | Decision                                                                                     | Alternatives rejected                                               |
| --- | -------------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| 1   | Space belongs directly to User; Cashier Billable on User                                     | Account/workspace layer; hybrid `user_id` + nullable `account_id`   |
| 2   | Slug globally unique, route `/s/{slug}`; `public_id` immutable embed key                     | Per-user slugs + `users.handle`; `public_id` in the public URL      |
| 3   | `space_fields` normalized table                                                              | JSON `field_configuration`; explicit enum columns per field         |
| 4   | Open field set: custom fields in scope, `testimonial_values` EAV                             | (none listed)                                                       |
| 5   | `name`, `email`, `testimonial`, `rating` as columns; variation in EAV                        | Pure EAV; all five predefined fields as columns                     |
| 6   | `is_public` STORED generated column + `idx_public`                                           | Eloquent scope only; single visibility enum                         |
| 7   | UTC-only day bucketing, no `users.timezone`                                                  | `users.timezone` + PHP bucketing; `users.timezone` + `CONVERT_TZ`   |
| 8   | Plan enum in code; plan derived from Cashier                                                 | `plans` table + `users.plan_id`; denormalized `users.plan`          |
| 9   | Live `COUNT(*)`, no lock, boundary race accepted                                             | `FOR UPDATE` transaction; `spaces.testimonials_count` counter cache |
| 10  | `space_fields.show_in_embed` + `embed_configurations.show_rating`; `display_options` dropped | `embed_field_visibility` pivot; `display_options` JSON              |
| 11  | Webhook idempotency by natural handler design                                                | `stripe_events` table + unique index; Redis `SETNX` dedup           |
| 12  | Stripe Dashboard as the log of record                                                        | Dedicated Stripe log channel; `stripe_events` audit table           |
| 13  | `consent_given` boolean, per PRD                                                             | `consented_at`; `consented_at` + `consent_text_version`             |
| 14  | Space soft delete; slug retained; deleted Spaces don't count toward the limit                | Hard delete + FK cascade; soft delete + slug mangling + purge job   |

---

## Appendix: Notes on Gaps

The following items from the source material were noted but not fully resolved:

- The "address" field from the instructor's brief is not in this model and §8 doesn't list it as a divergence. Likely a voice-prompt slip.
- Decision log #4 shows no rejected alternative; #5 says "all five predefined fields" although only name/email/testimonial/rating plus three seeded rows are described. Likely wording looseness.
- The Mermaid source behind the ERD's "Code" tab was not shown.
- The MySQL check for `deleted_at IS NULL` inside a STORED column isn't shown as tested in development.
- Photo size cap value was not specified.

---

**End of data model document.**
