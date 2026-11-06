# Remote Jobs — Paid Featured Listings (Stripe + PayPal)

**Date:** 2026-11-06
**Status:** Approved design — ready for implementation planning
**Scope:** `wp-content/plugins/remote-jobs`

## 1. Summary

Add paid **featured** job listings to the Remote Jobs plugin, payable by **Stripe**
or **PayPal**, under two plans:

- **Single (one-time):** a single payment features one job for a fixed number of
  days (default 30), then it expires.
- **Monthly (recurring subscription):** auto-renews monthly via Stripe Billing /
  PayPal Subscriptions and keeps job(s) featured while active; canceling or a
  failed renewal lets the feature expire.

Free job submission is unchanged (submit → `pending` → admin moderation). Paying is
an **optional featured upgrade**. A paid job **auto-publishes** once payment is
confirmed by webhook. When a paid period ends, an admin setting decides whether the
listing is **unpublished** or just **un-featured**.

## 2. Decisions (locked)

| # | Decision | Choice |
|---|---|---|
| 1 | Monthly plan billing | **Recurring subscription** (auto-renew) |
| 2 | What payment unlocks | **Featured upgrade only** — free listings still exist & are moderated |
| 3 | On expiry | **Admin setting**: `unpublish` or `unfeature` |
| 4 | Checkout style | **Hosted** (Stripe Checkout + PayPal hosted approval), webhook-confirmed |
| 5 | Monthly scope | **Admin setting**: `per-job` or `account-wide` (with optional cap) |
| 6 | Where to pay | **Both** — at submission and later via a "My Jobs" upgrade |
| 7 | Moderation for paid jobs | **Skip** — auto-publish on confirmed payment |
| 8 | Data model | **Approach A** — settings-based plans + custom tables for orders/subscriptions + post meta for featured state |
| 9 | Dependencies | **No bundled SDKs / no Composer** — call Stripe & PayPal REST APIs via `wp_remote_*` |

## 3. Module architecture

New code under `includes/payments/`, one class per file, `Remjobs_` prefix, matching
existing style. A `Remjobs_Payments` orchestrator is instantiated from
`Remjobs_Core::remjobs_load_dependencies()` (alongside `Remjobs_Job`).

| File | Class | Responsibility |
|---|---|---|
| `payments/class-remjobs-payments.php` | `Remjobs_Payments` | Bootstrap: load gateways, register REST webhook routes, cron, UI hooks |
| `payments/class-remjobs-plans.php` | `Remjobs_Plans` | Single source of truth for the 2 plans; resolves price/currency/duration/scope/cap from settings; server-side amount calc |
| `payments/interface-remjobs-gateway.php` | `Remjobs_Payment_Gateway` | Contract: `get_id()`, `create_checkout()`, `handle_webhook()`, `verify_signature()`, `cancel_subscription()` |
| `payments/gateways/class-remjobs-gateway-stripe.php` | `Remjobs_Gateway_Stripe` | Stripe Checkout (payment + subscription modes) + webhooks |
| `payments/gateways/class-remjobs-gateway-paypal.php` | `Remjobs_Gateway_PayPal` | PayPal Orders v2 (one-time) + Subscriptions v1 + webhooks |
| `payments/class-remjobs-orders.php` | `Remjobs_Orders` | CRUD + state transitions for the orders table |
| `payments/class-remjobs-subscriptions.php` | `Remjobs_Subscriptions` | CRUD + state for the subscriptions table |
| `payments/class-remjobs-featured.php` | `Remjobs_Featured` | Lifecycle: activate feature on payment, expire via cron, apply admin expiry behavior, enforce account cap |
| `payments/class-remjobs-payment-settings.php` | `Remjobs_Payment_Settings` | Admin settings page + Orders list screen |

**HTTP layer:** a tiny shared helper (inside each gateway) wraps `wp_remote_post`/
`wp_remote_get` with auth headers, JSON encode/decode, timeout, and error
normalization. No third-party SDKs.

## 4. Data model

### 4.1 Table `{$wpdb->prefix}remjobs_orders`
Audit trail + source of truth for one-time and initial-subscription payments.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `order_key` | VARCHAR(32) UNIQUE | random; used in return URLs (never exposes `id`) |
| `user_id` | BIGINT UNSIGNED | employer |
| `job_id` | BIGINT UNSIGNED NULL | job being featured (nullable until known) |
| `plan` | VARCHAR(20) | `single` \| `monthly` |
| `gateway` | VARCHAR(20) | `stripe` \| `paypal` |
| `gateway_order_id` | VARCHAR(255) NULL | Stripe session id / PayPal order id |
| `gateway_event_id` | VARCHAR(255) NULL | last processed webhook event id (idempotency) |
| `subscription_id` | BIGINT UNSIGNED NULL | FK → subscriptions.id (monthly) |
| `amount` | DECIMAL(10,2) | validated server-side from settings |
| `currency` | VARCHAR(3) | |
| `status` | VARCHAR(20) | `pending` \| `paid` \| `failed` \| `refunded` \| `canceled` |
| `created_at` / `updated_at` | DATETIME | UTC |

Indexes: `user_id`, `job_id`, `gateway_order_id`, `status`, UNIQUE `order_key`.

### 4.2 Table `{$wpdb->prefix}remjobs_subscriptions`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK AI | |
| `user_id` | BIGINT UNSIGNED | |
| `scope` | VARCHAR(10) | `job` \| `account` (captured at purchase from setting) |
| `job_id` | BIGINT UNSIGNED NULL | set when `scope=job` |
| `gateway` | VARCHAR(20) | |
| `gateway_subscription_id` | VARCHAR(255) | |
| `gateway_customer_id` | VARCHAR(255) NULL | |
| `status` | VARCHAR(20) | `active` \| `past_due` \| `canceled` \| `expired` |
| `current_period_end` | DATETIME | UTC; drives feature expiry |
| `cancel_at_period_end` | TINYINT(1) | |
| `created_at` / `updated_at` | DATETIME | |

Indexes: `user_id`, `gateway_subscription_id`, `status`.

### 4.3 Post meta on `remjobs_jobs` (featured state)

| Meta key | Meaning |
|---|---|
| `_remjobs_featured` | `'1'`/`''` — **canonical** "currently featured" flag |
| `_remjobs_featured_expires` | `Y-m-d H:i:s` UTC — when the current feature lapses |
| `_remjobs_feature_plan` | `single` \| `monthly` |
| `_remjobs_feature_order_id` | last paid `orders.id` |
| `_remjobs_feature_subscription_id` | `subscriptions.id` (monthly) |
| `_remjobs_payment_status` | `awaiting` \| `paid` \| `''` — holds unpaid-at-submission jobs |
| `_remjobs_expired` | `'1'` — set when an `unpublish`-behavior expiry drafts the job |

**Normalization (targeted cleanup):** today `featuredOnly` queries `_remjobs_is_premium`
while the badge reads `_remjobs_featured` — inconsistent. We make `_remjobs_featured`
canonical: update the `job-list` query + badge to both use it, keep `_remjobs_is_premium`
written in sync for backward compatibility, and add a one-time migration setting
`_remjobs_featured='1'` wherever `_remjobs_is_premium='1'`.

### 4.4 Settings option `remjobs_payment_settings` (array)

Keys: `enabled_gateways[]`, `mode` (`sandbox`|`live`), `currency` (default `USD`),
`stripe_publishable_key`, `stripe_secret_key`, `stripe_webhook_secret`,
`stripe_price_id_monthly` (auto-created + cached if blank),
`paypal_client_id`, `paypal_secret`, `paypal_webhook_id`,
`paypal_plan_id_monthly` (auto-created + cached if blank),
`single_price` (default `49.00`), `single_duration_days` (default `30`),
`monthly_price` (default `99.00`), `monthly_scope` (`job`|`account`, default `job`),
`monthly_account_cap` (int, `0`=unlimited, default `0`),
`expiry_behavior` (`unpublish`|`unfeature`, default `unfeature`).

**Secret override:** if constants `REMJOBS_STRIPE_SECRET`, `REMJOBS_PAYPAL_SECRET`,
etc. are defined in `wp-config.php`, they take precedence over stored options.

## 5. Payment flow

### 5.1 Create order & checkout (shared)
1. User triggers "Feature" (at submission or from My Jobs), choosing **plan** +
   **gateway**.
2. Server validates: login, ownership/cap, plan enabled, gateway enabled.
3. **Amount is resolved server-side** from `Remjobs_Plans` — never from the client
   (prevents price tampering).
4. `Remjobs_Orders::create()` inserts a `pending` order with a random `order_key`.
5. Gateway `create_checkout()` creates the hosted session/order and returns a
   redirect URL; success/cancel return URLs carry `order_key` + a nonce.
6. User is redirected to the gateway.

### 5.2 Confirmation (webhook = source of truth)
- Payment is **only** confirmed via signed webhook (the browser return is treated as
  advisory and also triggers a server-side verify as a fallback).
- On confirmed payment the handler is **idempotent** (dedupe by `gateway_event_id`;
  re-check order status before acting):
  - mark order `paid`;
  - for monthly: upsert a `subscriptions` row (`active`, `current_period_end`);
  - call `Remjobs_Featured::activate_feature($job_id, $plan, $order)`.

### 5.3 Auto-publish + holding status
- **Feature at submission:** job is created as `draft` with
  `_remjobs_payment_status='awaiting'` (so it is neither public nor in the moderation
  queue). On confirmed payment → `publish` + featured. Abandoned awaiting-payment
  drafts older than 48h are deleted by the cron sweep.
- **Upgrade existing job:** if the job was `pending`/`draft`, confirmed payment sets
  it to `publish`; if already `publish`, it simply becomes featured.
- Free submissions are untouched: still created as `pending`.

### 5.4 Gateway specifics

**Stripe**
- One-time: Checkout Session `mode=payment` with inline `price_data` (amount ×100,
  zero-decimal currencies handled).
- Monthly: Checkout Session `mode=subscription` using a recurring **Price** — auto-
  created (Product + monthly Price) on first use and cached in settings if not set.
- Webhooks: `checkout.session.completed` (one-time + first subscription invoice),
  `invoice.paid` (renewals → extend `current_period_end`),
  `invoice.payment_failed` (→ `past_due`),
  `customer.subscription.deleted`/`updated` (cancel/status).
- Signature: HMAC verify `Stripe-Signature` with `stripe_webhook_secret`.

**PayPal**
- One-time: Orders v2 — create order, redirect to approver link, **capture** on
  return and/or on `PAYMENT.CAPTURE.COMPLETED`.
- Monthly: Subscriptions v1 — Product + Billing Plan auto-created + cached; redirect
  to approval link.
- Webhooks: `PAYMENT.CAPTURE.COMPLETED`, `BILLING.SUBSCRIPTION.ACTIVATED`,
  `PAYMENT.SALE.COMPLETED` (renewals), `BILLING.SUBSCRIPTION.PAYMENT.FAILED`,
  `BILLING.SUBSCRIPTION.CANCELLED`/`EXPIRED`.
- Signature: POST to `/v1/notifications/verify-webhook-signature` with `paypal_webhook_id`.

## 6. Featured lifecycle & expiry

`Remjobs_Featured` centralizes all state changes:

- **activate_feature(job, plan, order|sub):** publish job; set `_remjobs_featured=1`
  (+ sync `_remjobs_is_premium`); set `_remjobs_featured_expires`:
  - single → `now + single_duration_days`;
  - monthly → `subscriptions.current_period_end` (+ small grace).
  For `account` scope, enforce `monthly_account_cap`: beyond the cap the Feature
  action is rejected with a clear message (user un-features another job or the admin
  raises the cap) — nothing is silently queued.
- **renewal:** extend every covered job's `_remjobs_featured_expires` to the new
  `current_period_end`.
- **expire_feature(job):** apply `expiry_behavior`:
  - `unfeature` → keep `publish`, clear featured meta;
  - `unpublish` → set `draft`, clear featured meta, set `_remjobs_expired=1`.

**Cron** `remjobs_feature_expiry_check` (hourly):
1. Find `publish` jobs with `_remjobs_featured=1` AND `_remjobs_featured_expires < now`
   that are **not** covered by an `active` subscription → `expire_feature()`.
2. Delete abandoned `awaiting`-payment drafts older than 48h.
Scheduled on activation; self-heals on `plugins_loaded` if missing; cleared on
deactivation. All time math in UTC.

**Account-wide vs per-job:** per-job subscriptions map 1:1 to a `job_id`; account-wide
subscriptions cover any of the employer's jobs up to the cap, and renewal/lapse
updates all of that user's featured jobs together.

## 7. UI touchpoints

- **Submit Job block:** add a plan chooser (Free / Feature — Single / Feature —
  Monthly) and, when featuring, a gateway choice. Paid path → create draft + order →
  redirect to gateway. Free path unchanged.
- **My Jobs** (`[remjobs_my_jobs]` block/shortcode): lists the user's jobs with
  status, featured + expiry, a **Feature** button (plan/gateway → checkout), and for
  active subscriptions a **Cancel subscription** control + renewal date.
- **Admin:** new "Payments" settings page under the Jobs menu (keys, mode, prices,
  scope, cap, expiry, currency); a read-only **Orders** list; and job-list columns
  for Featured / Expires / Payment status.
- **Return pages:** success/cancel handled on the My Jobs (or submit) page via
  `order_key` + nonce, showing a verified status message.

## 8. Security

- Webhook endpoints verify provider signatures; unverified requests rejected (401).
- Payment confirmation is server-side only (webhook + server verify); the client
  redirect never flips an order to paid.
- Amounts/plans resolved server-side from settings; client input ignored for pricing.
- Nonces on every form/AJAX action; capability checks (`publish_posts`; ownership for
  upgrade/cancel).
- All table access via `$wpdb->prepare`; sanitize inputs, escape outputs.
- Webhook processing idempotent (dedupe by event id; status re-check).
- Secrets may be supplied via `wp-config.php` constants (override stored options).

## 9. Error handling & edge cases

- Checkout-create API failure → user-facing error; order stays `pending`, job stays
  `draft`/unchanged.
- Webhook/return race → both converge idempotently.
- Subscription `payment_failed` → `past_due` + grace; still unpaid → expire.
- Refund/chargeback webhook → order `refunded`; feature expired per behavior.
- Trashing/deleting a job with an active per-job subscription → cancel that
  subscription at the gateway (hook `wp_trash_post`/`before_delete_post`).
- Zero-decimal currencies handled for Stripe amount conversion.
- Gateway disabled / keys missing → feature controls hidden; clear admin notice.

## 10. Testing strategy

- **Logic (pure):** plan/amount resolution incl. zero-decimal; order state machine;
  `activate_feature`/`expire_feature` branching for both `expiry_behavior` values;
  account cap enforcement; webhook idempotency/dedupe; UTC expiry math.
- **End-to-end (sandbox):** Stripe test mode + PayPal sandbox. Because this is a local
  Studio/SQLite site, provide a documented manual test plan plus a `studio wp eval`
  harness that: (a) simulates a signed paid webhook and asserts the job is published,
  featured, and has an expiry; (b) fast-forwards `_remjobs_featured_expires`, runs the
  cron callback, and asserts the configured expiry behavior; (c) simulates a renewal
  and a cancellation.
- No PHPUnit harness exists in the plugin today; add minimal bootstrap for the pure-
  logic tests, or at minimum ship the wp-cli verification harness. (Confirm during
  planning.)

## 11. Out of scope (YAGNI)

Invoices/PDF receipts (gateways email their own), coupons/discounts, tax/VAT,
arbitrary admin-created plans (only the two), proration on upgrade, an admin refund
button (refund *webhooks* are still handled).

## 12. Config defaults

`currency=USD`, `single_price=49.00`, `single_duration_days=30`,
`monthly_price=99.00`, `monthly_scope=job`, `monthly_account_cap=0`,
`expiry_behavior=unfeature`, `mode=sandbox`. All editable in settings.
