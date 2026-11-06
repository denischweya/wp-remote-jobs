# Paid Featured Listings — Phase 3a: Gateway Infra + Stripe — Implementation Plan

**Goal:** Build the gateway abstraction, the idempotent fulfillment service, the checkout coordinator, the webhook REST endpoints, and a complete **Stripe** gateway (hosted Checkout for one-time + subscription, signed webhooks) — plus the account-scope expiry-sweep protection carried over from the Phase 2 review. PayPal is Phase 3b (same interface).

**Architecture:** A `Remjobs_Payment_Gateway` interface with a Stripe implementation. `Remjobs_Checkout` validates a request, creates a `pending` order, and asks the gateway for a hosted redirect URL. Payment is confirmed **only** server-side: the gateway's `parse_webhook()` verifies the provider signature and returns a normalized event; `Remjobs_Fulfillment` idempotently marks the order paid, upserts any subscription, and calls the Phase 2 `Remjobs_Featured::activate_feature()`. All wired by the `Remjobs_Payments` orchestrator.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, WP REST API, `wp_remote_post`/`wp_remote_get` (NO SDKs), Stripe REST API, WP-CLI.

**Spec:** `docs/superpowers/specs/2026-11-06-remote-jobs-payments-design.md` (§5 flow, §8 security). **Prior code:** Phase 1 (`orders`, `subscriptions`, `plans`, `settings`) + Phase 2 (`featured`) under `includes/payments/`.

## Global Constraints (bind every task)

- PHP 7.4 floor; WP 5.8 floor; no PHP 8-only syntax. Text domain `remote-jobs`; `Remjobs_` prefix; one class per file; `declare(strict_types=1)` + `ABSPATH` guard. UTC timestamps.
- **No SDKs / no Composer** — call Stripe's REST API with `wp_remote_post`/`wp_remote_get`.
- **Payment is confirmed only by a signature-verified webhook** (plus an optional server-side return verify). The browser redirect NEVER marks an order paid.
- **Amounts come only from `Remjobs_Plans`** server-side; never from the client or the gateway response.
- Webhook processing is **idempotent**: dedupe by event id and re-check order status before acting.
- All custom-table access via `$wpdb->prepare`; sanitize input; escape output; nonce + capability checks on user-initiated actions.
- Secrets via `Remjobs_Payment_Settings::get()` (honors wp-config constants).
- Tests in `tests/wpcli/`, run as `studio wp eval-file wp-content/plugins/remote-jobs/tests/wpcli/test-NAME.php 2>&1 | grep -viE "Deprecated:|react/promise"`, must print `RESULT: PASS`. `php -l` is broken — validate only via `studio wp`.
- Match Phase 1/2 style (read `includes/payments/class-remjobs-featured.php`, `class-remjobs-orders.php`, `class-remjobs-payments.php` first).
- Commit per task on `feature/paid-featured-listings`; `git add` only the task's explicit files (never `-A`, never `docs/` or `.superpowers/`); every message ends with `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- The plugin is ACTIVE on a LIVE site — after wiring (Task 6) verify `curl -s -o /dev/null -w "%{http_code}" http://localhost:8881/` is `200` and no "critical error". If the site breaks, STOP (BLOCKED).

## Normalized webhook event shape (the contract between gateways and fulfillment)

```php
// Returned by Remjobs_Payment_Gateway::parse_webhook(); null if unverified/irrelevant.
array(
  'event_id'          => string,   // provider event id (idempotency)
  'kind'              => string,   // 'payment_succeeded' | 'subscription_renewed'
                                   //  | 'subscription_canceled' | 'subscription_payment_failed'
  'order_id'          => int|null, // OUR order id (from metadata/custom_id) — null for pure renewals
  'gateway_order_id'  => string,   // provider session/order id
  'subscription'      => null | array(
       'gateway_subscription_id' => string,
       'gateway_customer_id'     => string,
       'current_period_end'      => string|null, // 'Y-m-d H:i:s' UTC
       'status'                  => string,      // 'active'|'past_due'|'canceled'
  ),
)
```

## File Structure

| File | Responsibility |
|---|---|
| `includes/payments/interface-remjobs-gateway.php` *(create)* | `Remjobs_Payment_Gateway` interface |
| `includes/payments/class-remjobs-fulfillment.php` *(create)* | idempotent fulfill/renew/cancel; the only caller of `activate_feature` from webhooks |
| `includes/payments/class-remjobs-checkout.php` *(create)* | validate + create order + return redirect URL; server-side return verify |
| `includes/payments/class-remjobs-webhooks.php` *(create)* | REST routes `remjobs/v1/webhooks/{gateway}`; dispatch to gateway+fulfillment |
| `includes/payments/gateways/class-remjobs-gateway-stripe.php` *(create)* | Stripe Checkout + webhook verify + event mapping |
| `includes/payments/class-remjobs-subscriptions.php` *(modify)* | add `active_account_for_user()` |
| `includes/payments/class-remjobs-featured.php` *(modify)* | sweep also protects account-scope subscribers' jobs |
| `includes/payments/class-remjobs-payments.php` *(modify)* | load gateways; instantiate fulfillment/checkout/webhooks; register routes |
| `tests/wpcli/test-*.php` *(create)* | per task below |

---

### Task 1: Gateway interface + account-scope sweep protection

**Files:** Create `interface-remjobs-gateway.php`; modify `class-remjobs-subscriptions.php` and `class-remjobs-featured.php`; Test `tests/wpcli/test-account-protection.php`.

**Interface (exact):**
```php
<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

interface Remjobs_Payment_Gateway
{
    public function get_id(): string;                 // 'stripe' | 'paypal'
    public function is_configured(): bool;
    /** @return string hosted redirect URL; throws Exception on failure */
    public function create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string;
    /** @return array|null normalized event (see plan); null if unverified/irrelevant */
    public function parse_webhook(WP_REST_Request $request): ?array;
    public function cancel_subscription(string $gateway_subscription_id): bool;
}
```

**`Remjobs_Subscriptions::active_account_for_user` (add):**
```php
public static function active_account_for_user(int $user_id): ?array
{
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE user_id = %d AND scope = 'account' AND status = 'active' ORDER BY id DESC LIMIT 1", $user_id),
        ARRAY_A
    );
    return $row ?: null;
}
```

**`Remjobs_Featured::run_expiry_sweep` change:** when iterating lapsed featured jobs, before `expire_feature($id)`, skip if **either** `Remjobs_Subscriptions::active_for_job($id)` **or** `Remjobs_Subscriptions::active_account_for_user((int) get_post_field('post_author', $id))` is non-null. (Keep the existing per-job check; add the account check.)

**Test (`test-account-protection.php`):** create an account-scope active subscription for user 1 (no `job_id`), create a lapsed featured job authored by user 1, run the sweep, assert the job is **still featured** (account subscription protects it). Then set the subscription `canceled`, run sweep again, assert it now expires. Clean up.

**Steps:** write test → red → implement interface + method + sweep change → green → commit `feat: add gateway interface and account-scope expiry protection`.

---

### Task 2: Fulfillment service (idempotent)

**Files:** Create `class-remjobs-fulfillment.php`; Test `tests/wpcli/test-fulfillment.php`.

**Class (exact behavior):**
```php
class Remjobs_Fulfillment
{
    /** @var Remjobs_Featured */
    private $featured;
    public function __construct(Remjobs_Featured $featured) { $this->featured = $featured; }

    /** Idempotent. Marks order paid, upserts subscription (if any), activates the feature. */
    public function fulfill(int $order_id, array $event): void
    {
        $order = Remjobs_Orders::get($order_id);
        if (!$order || $order['status'] === 'paid') { return; }           // idempotent guard

        Remjobs_Orders::mark_paid($order_id, (string) ($event['gateway_order_id'] ?? ''), (string) ($event['event_id'] ?? ''));

        $context = array('order_id' => $order_id);
        if (!empty($event['subscription'])) {
            $sub   = $event['subscription'];
            $plan  = Remjobs_Plans::get('monthly');
            $scope = $plan ? (string) $plan['scope'] : 'job';
            $sub_id = Remjobs_Subscriptions::upsert((string) $order['gateway'], (string) $sub['gateway_subscription_id'], array(
                'user_id'             => (int) $order['user_id'],
                'scope'               => $scope,
                'job_id'              => $scope === 'job' ? (int) $order['job_id'] : null,
                'gateway_customer_id' => (string) ($sub['gateway_customer_id'] ?? ''),
                'status'              => 'active',
                'current_period_end'  => $sub['current_period_end'] ?? null,
            ));
            Remjobs_Orders::update($order_id, array('subscription_id' => $sub_id));
            $context['subscription_id']   = $sub_id;
            $context['current_period_end'] = $sub['current_period_end'] ?? null;
        }
        if (!empty($order['job_id'])) {
            $this->featured->activate_feature((int) $order['job_id'], (string) $order['plan'], $context);
        }
    }

    /** Renewal: extend every covered job's expiry to the new period end. */
    public function renew(array $event): void
    {
        if (empty($event['subscription']['gateway_subscription_id'])) { return; }
        $sub = Remjobs_Subscriptions::get_by_gateway_id_any($event['subscription']['gateway_subscription_id']); // see note
        if (!$sub) { return; }
        $cpe = $event['subscription']['current_period_end'] ?? null;
        if ($cpe) {
            Remjobs_Subscriptions::upsert((string) $sub['gateway'], (string) $sub['gateway_subscription_id'], array('status' => 'active', 'current_period_end' => $cpe));
            foreach ($this->covered_job_ids($sub) as $jid) { $this->featured->extend_feature((int) $jid, (string) $cpe); }
        }
    }

    public function cancel(array $event): void
    {
        if (empty($event['subscription']['gateway_subscription_id'])) { return; }
        $sub = Remjobs_Subscriptions::get_by_gateway_id_any($event['subscription']['gateway_subscription_id']);
        if ($sub) { Remjobs_Subscriptions::set_status((int) $sub['id'], 'canceled'); }
        // The hourly sweep expires the now-unprotected job(s) per the admin setting.
    }

    /** @return int[] job ids covered by a subscription (per-job: its job; account: all the user's featured jobs) */
    private function covered_job_ids(array $sub): array
    {
        if ((string) $sub['scope'] === 'account') {
            // reuse featured engine's counter query intent — list featured job ids for the user
            return get_posts(array('post_type'=>'remjobs_jobs','post_status'=>'publish','fields'=>'ids','posts_per_page'=>-1,'author'=>(int)$sub['user_id'],
                'meta_query'=>array(array('key'=>'_remjobs_featured','value'=>'1'))));
        }
        return !empty($sub['job_id']) ? array((int) $sub['job_id']) : array();
    }
}
```
> **Add helper** `Remjobs_Subscriptions::get_by_gateway_id_any(string $gateway_subscription_id): ?array` (lookup by `gateway_subscription_id` across gateways — webhooks for renewals may not carry the gateway label conveniently). Implement with `$wpdb->prepare` and `LIMIT 1`.

**Test (`test-fulfillment.php`):** create a real job (draft) + a `pending` single order for it; call `fulfill($order_id, ['event_id'=>'e1','gateway_order_id'=>'cs_1'])`; assert order `paid`, job `publish` + featured. Call `fulfill` again with the same args → assert **no double-processing** (still one paid order, still featured; use a second job/order to confirm idempotency guard returns early — e.g., assert `mark_paid` wasn't needed). Then a subscription event: `fulfill` a monthly order with `subscription`→ assert a subscriptions row exists, order has `subscription_id`, job featured with expiry = period end. Clean up.

**Steps:** write test → red → implement (+ the two subscription helpers) → green → commit `feat: add idempotent payment fulfillment service`.

---

### Task 3: Stripe gateway — checkout

**Files:** Create `gateways/class-remjobs-gateway-stripe.php`; Test `tests/wpcli/test-stripe-checkout.php`.

**`create_checkout` behavior (Stripe Checkout Sessions, `POST https://api.stripe.com/v1/checkout/sessions`, `Authorization: Bearer <secret>`, form-encoded):**
- Common: `success_url=$return_url`, `cancel_url=$cancel_url`, `client_reference_id=$order['order_key']`, `metadata[order_id]=$order['id']`.
- One-time (`$plan['type']==='one_time'`): `mode=payment`, `line_items[0][quantity]=1`, `line_items[0][price_data][currency]=strtolower($plan['currency'])`, `line_items[0][price_data][unit_amount]=Remjobs_Plans::amount_minor('single')`, `line_items[0][price_data][product_data][name]='Featured job listing'`.
- Subscription (`type==='subscription'`): `mode=subscription`, `line_items[0][quantity]=1`, `line_items[0][price]=<monthly price id>`. If `stripe_price_id_monthly` setting is empty, call `ensure_monthly_price()`: create a Product (`POST /v1/products` name=`Featured listing — monthly`) then a recurring Price (`POST /v1/prices` `unit_amount`=amount_minor('monthly'), `currency`, `recurring[interval]=month`, `product`=id), persist the price id back into settings, and use it. Also set `subscription_data[metadata][order_id]=$order['id']`.
- Parse JSON response; return `url`. On non-2xx or missing `url`, throw `Exception` with the Stripe error message.
- Use a private `request(string $method, string $path, array $params)` helper (`wp_remote_post`/`get` with `Authorization` header, `timeout`=20, `body`=$params form-encoded). Return decoded array; throw on WP_Error or `error` in body.

**Test (`test-stripe-checkout.php`)** — stub Stripe HTTP with the `pre_http_request` filter (no network):
```php
<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'class-remjobs-plans.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-stripe.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency'=>'USD','single_price'=>'49.00','stripe_secret_key'=>'sk_test_x','stripe_price_id_monthly'=>'price_123',
)));

$captured = array();
add_filter('pre_http_request', function ($pre, $args, $url) use (&$captured) {
    $captured[] = array('url'=>$url, 'body'=>$args['body']);
    return array('response'=>array('code'=>200), 'body'=>json_encode(array('id'=>'cs_test_1','url'=>'https://checkout.stripe.com/c/cs_test_1')));
}, 10, 3);

$g = new Remjobs_Gateway_Stripe();
$order = array('id'=>42,'order_key'=>'rjk_abc','user_id'=>1,'job_id'=>7,'plan'=>'single','gateway'=>'stripe');
$url = $g->create_checkout($order, Remjobs_Plans::get('single'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url === 'https://checkout.stripe.com/c/cs_test_1', 'returns stripe checkout url');
remjobs_t_assert(strpos($captured[0]['url'], '/v1/checkout/sessions') !== false, 'calls sessions endpoint');
remjobs_t_assert($captured[0]['body']['mode'] === 'payment', 'one-time uses payment mode');
remjobs_t_assert((string)$captured[0]['body']['metadata']['order_id'] === '42', 'order id in metadata');
remjobs_t_assert((int)$captured[0]['body']['line_items'][0]['price_data']['unit_amount'] === 4900, 'amount in cents from server');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
```
> If Stripe's form-encoding of nested arrays makes asserting `$captured[0]['body']` awkward, build `$params` as a nested array and let `wp_remote_post` encode it; assert against the nested array as shown (WP passes `body` through before encoding, so the filter sees the array).

**Steps:** write test → red → implement `create_checkout` + `request` + `ensure_monthly_price` + `get_id`/`is_configured` → green → commit `feat: add Stripe checkout session creation`.

---

### Task 4: Stripe gateway — webhook verification + event mapping

**Files:** Modify `gateways/class-remjobs-gateway-stripe.php`; Test `tests/wpcli/test-stripe-webhook.php`.

**`parse_webhook(WP_REST_Request $request): ?array`:**
- `$payload = $request->get_body();` `$sig = $request->get_header('stripe_signature');` `$secret = Remjobs_Payment_Settings::get('stripe_webhook_secret');`
- **Verify (exact):** parse `$sig` into `t=` and `v1=` parts; `$signed = $t . '.' . $payload;` `$expected = hash_hmac('sha256', $signed, $secret);` reject (return null) unless `hash_equals($expected, $v1)` AND `abs(time() - (int)$t) <= 300`.
- Decode `$payload` JSON → `$event`. `event_id = $event['id']`. Map `$event['type']`:
  - `checkout.session.completed`: `$s=$event['data']['object']; order_id=(int)$s['metadata']['order_id']; gateway_order_id=$s['id'];` if `$s['mode']==='subscription'`: fetch the subscription (`GET /v1/subscriptions/{$s['subscription']}`) to read `current_period_end` (unix→`gmdate`) + `customer`; build `subscription` array. kind `payment_succeeded`.
  - `invoice.paid`: kind `subscription_renewed`; `subscription.gateway_subscription_id=$obj['subscription']`; `current_period_end` from `$obj['lines']['data'][0]['period']['end']` (unix→gmdate); `order_id=null`.
  - `invoice.payment_failed`: kind `subscription_payment_failed`; subscription id from `$obj['subscription']`.
  - `customer.subscription.deleted`: kind `subscription_canceled`; subscription id from `$obj['id']`.
  - any other type → return null.

**Test (`test-stripe-webhook.php`)** — compute a REAL signature so verification is genuinely exercised; stub the `GET /v1/subscriptions` fetch via `pre_http_request`:
```php
<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-stripe.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('stripe_webhook_secret'=>'whsec_test')));

$body = json_encode(array('id'=>'evt_1','type'=>'checkout.session.completed','data'=>array('object'=>array(
    'id'=>'cs_1','mode'=>'payment','metadata'=>array('order_id'=>'42')))));
$t = time();
$sig = hash_hmac('sha256', $t . '.' . $body, 'whsec_test');
$req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$req->set_body($body);
$req->set_header('stripe_signature', "t={$t},v1={$sig}");

$g = new Remjobs_Gateway_Stripe();
$evt = $g->parse_webhook($req);
remjobs_t_assert(is_array($evt), 'valid signature parses');
remjobs_t_assert($evt['kind'] === 'payment_succeeded', 'kind mapped');
remjobs_t_assert($evt['order_id'] === 42, 'order id extracted');
remjobs_t_assert($evt['event_id'] === 'evt_1', 'event id');

// Tampered signature -> rejected
$req->set_header('stripe_signature', "t={$t},v1=deadbeef");
remjobs_t_assert($g->parse_webhook($req) === null, 'bad signature rejected');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
```

**Steps:** write test → red → implement `parse_webhook` (+ a `get_subscription()` helper used for subscription-mode sessions) + `cancel_subscription` (`POST /v1/subscriptions/{id}` with `cancel_at_period_end=true` or `DELETE`) → green → commit `feat: add Stripe webhook verification and event mapping`.

---

### Task 5: Checkout coordinator + webhook REST routes

**Files:** Create `class-remjobs-checkout.php`, `class-remjobs-webhooks.php`; Test `tests/wpcli/test-checkout.php`.

**`Remjobs_Checkout`:**
```php
class Remjobs_Checkout
{
    private $gateways; // array id => Remjobs_Payment_Gateway
    private $featured; // Remjobs_Featured (for can_feature)
    public function __construct(array $gateways, Remjobs_Featured $featured) {...}

    /** @return string redirect URL; throws on validation/gateway error */
    public function start(int $user_id, int $job_id, string $plan_id, string $gateway_id): string
    {
        $plan = Remjobs_Plans::get($plan_id);
        if (!$plan) { throw new Exception(__('Unknown plan.', 'remote-jobs')); }
        if (!isset($this->gateways[$gateway_id]) || !$this->gateways[$gateway_id]->is_configured()) {
            throw new Exception(__('Payment method unavailable.', 'remote-jobs'));
        }
        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'remjobs_jobs' || (int) $job->post_author !== $user_id) {
            throw new Exception(__('You cannot pay for this job.', 'remote-jobs'));
        }
        if ($plan_id === 'monthly' && $plan['scope'] === 'account' && !$this->featured->can_feature($user_id)) {
            throw new Exception(__('You have reached your featured-listing limit.', 'remote-jobs'));
        }
        if ($plan_id !== 'monthly' && !$this->can_feature_single($user_id)) { /* single always allowed */ }

        $order_id = Remjobs_Orders::create(array(
            'user_id'=>$user_id,'job_id'=>$job_id,'plan'=>$plan_id,'gateway'=>$gateway_id,
            'amount'=>$plan['price'],'currency'=>$plan['currency'],
        ));
        $order = Remjobs_Orders::get($order_id);
        $return = add_query_arg(array('remjobs_return'=>$order['order_key'], '_wpnonce'=>wp_create_nonce('remjobs_return_'.$order['order_key'])), $this->account_url());
        $cancel = add_query_arg(array('remjobs_cancel'=>'1'), $this->account_url());
        $url = $this->gateways[$gateway_id]->create_checkout($order, $plan, $return, $cancel);
        Remjobs_Orders::update($order_id, array('gateway_order_id' => $this->read_gateway_order_id($url, $gateway_id)));
        return $url;
    }
    // account_url(): home_url('/') for now (Phase 4 points it at the My Jobs page). Make it a filterable method returning home_url('/').
}
```
> Keep `start()` focused: validation → order → gateway. Drop the `can_feature_single` placeholder — single is always allowed. `read_gateway_order_id` is optional (the webhook carries the id); if awkward, skip storing it here.

**`Remjobs_Webhooks`:**
```php
class Remjobs_Webhooks
{
    private $gateways; private $fulfillment;
    public function __construct(array $gateways, Remjobs_Fulfillment $fulfillment) {...}
    public function register_routes(): void
    {
        register_rest_route('remjobs/v1', '/webhooks/(?P<gateway>[a-z]+)', array(
            'methods' => 'POST', 'permission_callback' => '__return_true', 'callback' => array($this, 'handle'),
        ));
    }
    public function handle(WP_REST_Request $request)
    {
        $gid = $request->get_param('gateway');
        if (!isset($this->gateways[$gid])) { return new WP_REST_Response(array('ok'=>false), 404); }
        $event = $this->gateways[$gid]->parse_webhook($request);
        if ($event === null) { return new WP_REST_Response(array('ok'=>false), 400); } // bad signature / irrelevant
        switch ($event['kind']) {
            case 'payment_succeeded':
                if (!empty($event['order_id'])) { $this->fulfillment->fulfill((int) $event['order_id'], $event); }
                break;
            case 'subscription_renewed':         $this->fulfillment->renew($event); break;
            case 'subscription_canceled':
            case 'subscription_payment_failed':  $this->fulfillment->cancel($event); break;
        }
        return new WP_REST_Response(array('ok'=>true), 200);
    }
}
```

**Test (`test-checkout.php`):** stub the gateway with an anonymous class implementing `Remjobs_Payment_Gateway` (is_configured→true, create_checkout→returns a known URL and records the order passed). Create a real job owned by user 1. Call `start(1, $job, 'single', 'stub')` → assert returns the URL, a `pending` order row was created with the right plan/amount/currency. Then assert ownership guard: `start(1, $otherUsersJob, ...)` throws. Then account-cap guard with scope=account + cap already reached → throws. Clean up.

**Steps:** write test → red → implement both classes → green → commit `feat: add checkout coordinator and webhook REST endpoints`.

---

### Task 6: Orchestrator wiring + live smoke

**Files:** Modify `class-remjobs-payments.php`; Test `tests/wpcli/test-stripe-wiring.php`.

**Behavior:** in `__construct`, require the interface, fulfillment, checkout, webhooks, and the Stripe gateway. Build `$this->gateways = array('stripe' => new Remjobs_Gateway_Stripe())` (PayPal added in 3b). Instantiate `$this->fulfillment = new Remjobs_Fulfillment($this->featured)`, `$this->checkout = new Remjobs_Checkout($this->gateways, $this->featured)`, `$this->webhooks = new Remjobs_Webhooks($this->gateways, $this->fulfillment)`. In `init()`, add `add_action('rest_api_init', array($this->webhooks, 'register_routes'));`. Expose `public function checkout(): Remjobs_Checkout { return $this->checkout; }` for Phase 4.

**Test (`test-stripe-wiring.php`):**
```php
<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Gateway_Stripe'), 'stripe gateway loaded');
remjobs_t_assert(class_exists('Remjobs_Fulfillment'), 'fulfillment loaded');
remjobs_t_assert(class_exists('Remjobs_Webhooks'), 'webhooks loaded');
remjobs_t_assert(interface_exists('Remjobs_Payment_Gateway'), 'gateway interface loaded');
remjobs_t_assert(method_exists($p, 'checkout'), 'checkout accessor present');
remjobs_t_done();
```
Then verify the REST route registers: `studio wp eval 'do_action("rest_api_init"); $r = rest_get_server()->get_routes(); echo isset($r["/remjobs/v1/webhooks/(?P<gateway>[a-z]+)"]) ? "ROUTE_OK" : "NO_ROUTE";'` → `ROUTE_OK`.

**Live smoke:** `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8881/` = `200`; homepage has no "critical error".

**Full regression:** run every `tests/wpcli/test-*.php`; all `RESULT: PASS`.

**Steps:** write test → red → implement wiring → green → route check + smoke + regression → commit `feat: wire Stripe gateway, fulfillment, checkout and webhooks into orchestrator`.

## Self-Review (plan vs spec §5/§8 + Phase 2 carry-over)

- Gateway interface + Stripe (checkout + verified webhooks + events) → Tasks 1,3,4. ✅
- Idempotent fulfillment (order→paid→subscription→feature), renewal, cancel → Task 2. ✅
- Checkout validation (ownership, plan/gateway enabled, account cap) + server-side order/amount → Task 5. ✅
- Webhook endpoints, signature-verified, idempotent, payment confirmed server-side only → Tasks 4,5. ✅
- Account-scope sweep protection (Phase 2 review carry-over) → Task 1. ✅
- Deferred to 3b: PayPal gateway (same interface). Deferred to Phase 4: submit/My-Jobs/orders UI, pointing `account_url()` at the My Jobs page, return-page verify UX.
