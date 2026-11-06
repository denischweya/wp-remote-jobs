# Paid Featured Listings — Phase 3b: PayPal Gateway — Implementation Plan

**Goal:** Add a **PayPal** gateway implementing the existing `Remjobs_Payment_Gateway` interface — hosted approval for one-time (Orders v2) and subscription (Subscriptions v1) payments, plus API-based webhook signature verification and event mapping into the same normalized event the fulfillment service already consumes. No changes to fulfillment/checkout/webhook routing beyond registering the gateway.

**Architecture:** `Remjobs_Gateway_PayPal` plugs into the `$gateways` map in the orchestrator. The webhook route `remjobs/v1/webhooks/paypal` already exists (dispatches by the `{gateway}` param); PayPal's `parse_webhook()` returns the same normalized event shape Stripe does, so `Remjobs_Fulfillment` needs zero changes.

**Tech Stack:** PHP 7.4+, WP 5.8+, `wp_remote_post`/`wp_remote_get` (NO SDKs), PayPal REST API v1/v2, WP-CLI.

**Spec:** `docs/.../2026-11-06-remote-jobs-payments-design.md` (§5.4 PayPal). **Depends on:** Phase 3a (interface, fulfillment, checkout, webhooks, Stripe) — read those files first:
`includes/payments/interface-remjobs-gateway.php`, `gateways/class-remjobs-gateway-stripe.php`, `class-remjobs-webhooks.php`, `class-remjobs-fulfillment.php`, `class-remjobs-payments.php`.

## Normalized event contract (MUST match Stripe's output exactly)
Return from `parse_webhook()`: `null` ONLY on verification failure; `array('kind'=>'ignored','event_id'=>...)` for a verified-but-unmapped event; otherwise:
```php
array(
  'event_id' => string, 'kind' => 'payment_succeeded'|'subscription_renewed'|'subscription_canceled'|'subscription_payment_failed',
  'order_id' => int|null, 'gateway_order_id' => string,
  'subscription' => null | array('gateway_subscription_id'=>string,'gateway_customer_id'=>string,'current_period_end'=>string|null,'status'=>string),
)
```

## Global Constraints (bind every task)
- PHP 7.4 floor; WP 5.8 floor; no PHP 8-only syntax. `Remjobs_` prefix; `declare(strict_types=1)`; `ABSPATH` guard; text domain `remote-jobs`; UTC timestamps.
- NO SDKs — `wp_remote_*` only. Amounts from `Remjobs_Plans` (server side). Payment confirmed server-side only (verified webhook). All custom SQL via `$wpdb->prepare`.
- Base URL by `mode` setting: `sandbox` → `https://api-m.sandbox.paypal.com`, `live` → `https://api-m.paypal.com`.
- Tests in `tests/wpcli/`, run `studio wp eval-file wp-content/plugins/remote-jobs/tests/wpcli/test-NAME.php 2>&1 | grep -viE "Deprecated:|react/promise"` → `RESULT: PASS`. Stub ALL PayPal HTTP with the `pre_http_request` filter — never hit the network. `php -l` broken → validate via `studio wp`.
- Match the Stripe gateway's structure/style. Commit per task on `feature/paid-featured-listings`; `git add` only explicit files (never `-A`/`docs/`/`.superpowers/`); every message ends with `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- Plugin is ACTIVE on a LIVE site — after wiring (Task 4) verify homepage `200` + no "critical error" or STOP (BLOCKED).

## File Structure
| File | Responsibility |
|---|---|
| `includes/payments/gateways/class-remjobs-gateway-paypal.php` *(create)* | PayPal gateway: OAuth, checkout (orders + subscriptions), webhook verify + event map, cancel |
| `includes/payments/class-remjobs-payments.php` *(modify)* | add `'paypal' => new Remjobs_Gateway_PayPal()` to the gateways map |
| `tests/wpcli/test-paypal-checkout.php` *(create)* | order + subscription checkout request construction |
| `tests/wpcli/test-paypal-webhook.php` *(create)* | signature-verify call + event mapping (incl. ignored + failure) |
| `tests/wpcli/test-paypal-wiring.php` *(create)* | gateway registered in orchestrator |

---

### Task 1: OAuth + request helper + `get_id`/`is_configured`

**Files:** Create `gateways/class-remjobs-gateway-paypal.php`; Test (folded into Task 2's test, or a tiny `test-paypal-oauth.php`).

**Behavior:**
- `get_id(): string` → `'paypal'`.
- `is_configured(): bool` → `Remjobs_Payment_Settings::get('paypal_client_id')` and `get('paypal_secret')` both non-empty.
- `private function base_url(): string` → sandbox/live by `mode`.
- `private function access_token(): string` — cache in a transient `remjobs_paypal_token` (TTL = response `expires_in` minus 60s). POST `base_url().'/v1/oauth2/token'` with header `Authorization: Basic ` . base64(`client_id:secret`) and body `grant_type=client_credentials`. Throw on WP_Error/non-2xx/missing `access_token`.
- `private function request(string $method, string $path, array $body = null, bool $json = true): array` — Bearer `access_token()`; `Content-Type: application/json` + `wp_json_encode($body)` when `$json`; `timeout`=20. Decode JSON; throw `Exception` on WP_Error, on HTTP ≥ 400 (include PayPal error `message`/`name`), return decoded array (or `array()` for empty 2xx like 204).

**Steps:** write a minimal OAuth test (stub `/v1/oauth2/token` via `pre_http_request`, assert `access_token()` returns the token and caches it — second call makes no new HTTP request) → red → implement → green → commit `feat: add PayPal gateway OAuth and request helper`.

---

### Task 2: Checkout — Orders v2 (one-time) + Subscriptions v1 (monthly)

**Files:** Modify the PayPal gateway; Test `tests/wpcli/test-paypal-checkout.php`.

**`create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string`:**
- Amount string: `number_format((float) $plan['price'], Remjobs_Plans::is_zero_decimal($plan['currency']) ? 0 : 2, '.', '')`. (If no such helper exists, inline: zero-decimal set is on `Remjobs_Plans::ZERO_DECIMAL`.)
- **One-time** (`$plan['type']==='one_time'`): POST `/v2/checkout/orders`:
  ```php
  array('intent'=>'CAPTURE',
    'purchase_units'=>array(array(
      'custom_id'=>(string)$order['id'],
      'description'=>'Featured job listing',
      'amount'=>array('currency_code'=>$plan['currency'],'value'=>$amount))),
    'application_context'=>array('return_url'=>$return_url,'cancel_url'=>$cancel_url,'user_action'=>'PAY_NOW','shipping_preference'=>'NO_SHIPPING'))
  ```
  From the response `links`, return the `href` whose `rel==='approve'`. Throw if absent.
- **Subscription** (`type==='subscription'`): ensure a billing plan id:
  - `private function ensure_plan_id(array $plan): string` — if `Remjobs_Payment_Settings::get('paypal_plan_id_monthly')` non-empty, return it. Else: create a Product (POST `/v1/catalogs/products` `{name:'Featured listing — monthly', type:'SERVICE'}`), then a Plan (POST `/v1/billing/plans` with `product_id`, `billing_cycles=[{frequency:{interval_unit:'MONTH',interval_count:1}, tenure_type:'REGULAR', sequence:1, total_cycles:0, pricing_scheme:{fixed_price:{value:$amount,currency_code:$plan['currency']}}}]`, `payment_preferences:{auto_bill_outstanding:true}`). Persist the plan id into settings (`update_option` merge) and return it.
  - POST `/v1/billing/subscriptions`: `{plan_id, custom_id:(string)$order['id'], application_context:{return_url, cancel_url, user_action:'SUBSCRIBE_NOW', shipping_preference:'NO_SHIPPING'}}`. Return the `approve` link href.

**Test (`test-paypal-checkout.php`)** — stub OAuth + the create calls via `pre_http_request`, routing by URL:
```php
add_filter('pre_http_request', function($pre,$args,$url){
  if (strpos($url,'/v1/oauth2/token')!==false) return array('response'=>array('code'=>200),'body'=>json_encode(array('access_token'=>'A','expires_in'=>3600)));
  if (strpos($url,'/v2/checkout/orders')!==false) { $GLOBALS['cap']=json_decode($args['body'],true); return array('response'=>array('code'=>201),'body'=>json_encode(array('id'=>'PPORDER1','links'=>array(array('rel'=>'approve','href'=>'https://paypal/approve/PPORDER1'))))); }
  return $pre;
},10,3);
```
Assert: one-time `create_checkout` returns `https://paypal/approve/PPORDER1`; the posted body `intent==='CAPTURE'`, `purchase_units[0]['custom_id']==='<order id>'`, `amount.value==='49.00'`, `amount.currency_code==='USD'`. Add a subscription case (stub `/v1/billing/subscriptions` returning an approve link + set `paypal_plan_id_monthly` so `ensure_plan_id` short-circuits) asserting the approve URL is returned and `custom_id` is the order id.

**Steps:** write test → red → implement → green → commit `feat: add PayPal order and subscription checkout`.

---

### Task 3: Webhook verification + event mapping + cancel

**Files:** Modify the PayPal gateway; Test `tests/wpcli/test-paypal-webhook.php`.

**`parse_webhook(WP_REST_Request $request): ?array`:**
- Read headers (WP lowercases + underscores): `paypal_auth_algo`, `paypal_cert_url`, `paypal_transmission_id`, `paypal_transmission_sig`, `paypal_transmission_time`. Body = `$request->get_body()`; decode to `$event`.
- **Verify:** POST `/v1/notifications/verify-webhook-signature` with:
  ```php
  array('auth_algo'=>$algo,'cert_url'=>$cert,'transmission_id'=>$tid,'transmission_sig'=>$sig,'transmission_time'=>$ttime,
        'webhook_id'=>Remjobs_Payment_Settings::get('paypal_webhook_id'),'webhook_event'=>$event)
  ```
  If the call fails or `verification_status !== 'SUCCESS'` → return `null`.
- Map `$event['event_type']` (`event_id = $event['id']`; `$r = $event['resource']`):
  - `CHECKOUT.ORDER.APPROVED` → capture the order server-side: POST `/v2/checkout/orders/{$r['id']}/capture` (empty body). Return `array('kind'=>'ignored','event_id'=>...)` (the resulting `PAYMENT.CAPTURE.COMPLETED` drives fulfillment). If capture throws, let it 500 so PayPal retries.
  - `PAYMENT.CAPTURE.COMPLETED` → `payment_succeeded`; `order_id=(int)$r['custom_id']`; `gateway_order_id=$r['id']`; `subscription=null`.
  - `BILLING.SUBSCRIPTION.ACTIVATED` → `payment_succeeded`; `order_id=(int)$r['custom_id']`; `gateway_order_id=$r['id']`; `subscription=array('gateway_subscription_id'=>$r['id'],'gateway_customer_id'=>$r['subscriber']['payer_id'] ?? '', 'current_period_end'=>paypal_date($r['billing_info']['next_billing_time'] ?? null), 'status'=>'active')`.
  - `PAYMENT.SALE.COMPLETED` → `subscription_renewed`; `subscription.gateway_subscription_id = $r['billing_agreement_id'] ?? ''`; `current_period_end` unknown here → set `null` (renewal handler keeps status active; expiry extension may rely on a follow-up `BILLING.SUBSCRIPTION.UPDATED`/period from the subscription GET — acceptable: if null, `renew()` no-ops the extension but keeps the sub active). `order_id=null`.
  - `BILLING.SUBSCRIPTION.CANCELLED` / `BILLING.SUBSCRIPTION.EXPIRED` / `BILLING.SUBSCRIPTION.SUSPENDED` → `subscription_canceled`; `subscription.gateway_subscription_id=$r['id']`.
  - anything else → `array('kind'=>'ignored','event_id'=>$event['id'])`.
- `private function paypal_date(?string $iso): ?string` — convert ISO-8601 (e.g. `2026-12-06T10:00:00Z`) to `gmdate('Y-m-d H:i:s', strtotime($iso))`, or null.
- `cancel_subscription(string $id): bool` → POST `/v1/billing/subscriptions/{$id}/cancel` with `{reason:'...'}`; return true on 2xx.

**Test (`test-paypal-webhook.php`)** — stub OAuth + the verify endpoint; assert mapping:
```php
add_filter('pre_http_request', function($pre,$args,$url){
  if (strpos($url,'/v1/oauth2/token')!==false) return array('response'=>array('code'=>200),'body'=>json_encode(array('access_token'=>'A','expires_in'=>3600)));
  if (strpos($url,'/v1/notifications/verify-webhook-signature')!==false) return array('response'=>array('code'=>200),'body'=>json_encode(array('verification_status'=>$GLOBALS['vstatus'])));
  return $pre;
},10,3);
```
Cases: with `$GLOBALS['vstatus']='SUCCESS'` and a `PAYMENT.CAPTURE.COMPLETED` body (`resource.custom_id='42'`) → `kind==='payment_succeeded'`, `order_id===42`. A `BILLING.SUBSCRIPTION.ACTIVATED` body → `subscription` populated, `current_period_end` is a `Y-m-d H:i:s`. An unmapped `event_type` with SUCCESS → `kind==='ignored'`. With `$GLOBALS['vstatus']='FAILURE'` → `parse_webhook` returns `null`. (Set the five `paypal_*` headers on the `WP_REST_Request` in each case.)

**Steps:** write test → red → implement → green → commit `feat: add PayPal webhook verification and event mapping`.

---

### Task 4: Orchestrator wiring + live smoke + full regression

**Files:** Modify `class-remjobs-payments.php`; Test `tests/wpcli/test-paypal-wiring.php`.

**Behavior:** `require_once` the PayPal gateway in `__construct`; add `'paypal' => new Remjobs_Gateway_PayPal()` to the `$this->gateways` map (alongside `'stripe'`).

**Test (`test-paypal-wiring.php`):**
```php
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Gateway_PayPal'), 'paypal gateway loaded');
$g = new Remjobs_Gateway_PayPal();
remjobs_t_assert($g->get_id() === 'paypal', 'paypal id');
remjobs_t_assert($g instanceof Remjobs_Payment_Gateway, 'implements interface');
remjobs_t_done();
```
Then: full regression (every `tests/wpcli/test-*.php` → `RESULT: PASS`); homepage `200`, no "critical error"; both webhook routes resolve (`.../webhooks/stripe` and `.../webhooks/paypal` both 400 on an unsigned POST, proving the route + gateway dispatch work).

**Steps:** write test → red → implement wiring → green → regression + smoke → commit `feat: register PayPal gateway in orchestrator`.

## Self-Review (plan vs spec §5.4)
- OAuth + request helper → Task 1. ✅
- Orders v2 one-time + Subscriptions v1 (with product/plan bootstrap) → Task 2. ✅
- API webhook verification + event mapping (capture-on-approve, payment, activate, renew, cancel) + cancel → Task 3. ✅
- Same normalized event → fulfillment unchanged; orchestrator registers gateway → Task 4. ✅
- Deferred to Phase 4: UI, return-page capture fallback UX, pointing `account_url()` at My Jobs.
