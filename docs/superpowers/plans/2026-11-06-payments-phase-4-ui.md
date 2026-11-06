# Paid Featured Listings — Phase 4: UI — Implementation Plan

**Goal:** Wire the (already built + reviewed) payment engine to user-facing surfaces: a plan/gateway chooser in the Submit Job form that routes paid submissions through checkout, a `[remjobs_my_jobs]` dashboard to feature existing jobs and cancel subscriptions, return/cancel notices, a read-only admin Orders list, and admin job-list columns.

**Architecture:** A new `Remjobs_Payment_Ui` service (held by the orchestrator, given the `Remjobs_Checkout`, gateways, and `Remjobs_Featured`) owns all UI hooks: two `admin-post.php` handlers (start checkout, cancel subscription), the My Jobs shortcode, return handling, the admin Orders page, and job columns. The Submit Job block's existing handler is extended to route paid plans into checkout. Money decisions still live in the engine; this phase is glue.

**Tech Stack:** PHP 7.4+, WP 5.8+, Shortcode + Settings/admin APIs, `admin-post.php`, WP-CLI.

**Spec:** `docs/.../2026-11-06-remote-jobs-payments-design.md` (§5.3 auto-publish/holding, §7 UI). **Depends on:** Phases 1-3. Read first: `includes/payments/class-remjobs-checkout.php`, `class-remjobs-payments.php`, `class-remjobs-featured.php`, `class-remjobs-orders.php`, `class-remjobs-subscriptions.php`, `class-remjobs-payment-settings.php`, and `includes/blocks/submit-job/submit-job.php`.

## Global Constraints (bind every task)
- PHP 7.4 floor; WP 5.8 floor. `Remjobs_` prefix; `declare(strict_types=1)` + `ABSPATH` guard in new class files; text domain `remote-jobs`. UTC timestamps.
- **Security:** every form/action uses a nonce + `is_user_logged_in()` + `current_user_can('publish_posts')`; ownership checked on job/subscription actions (`(int) $post->post_author === get_current_user_id()`); **escape all output** (`esc_html/esc_attr/esc_url`), sanitize all input (`sanitize_text_field`, `absint`). Custom SQL via `$wpdb->prepare`.
- Free submissions keep the existing `pending` flow unchanged. Paid submissions become `draft` + `_remjobs_payment_status='awaiting'` and go to checkout; the webhook (Phase 3) publishes + features on payment.
- Tests in `tests/wpcli/`, run `studio wp eval-file wp-content/plugins/remote-jobs/tests/wpcli/test-NAME.php 2>&1 | grep -viE "Deprecated:|react/promise"` → `RESULT: PASS`. `php -l` broken → validate via `studio wp`. Redirect/`exit` handlers can't be unit-tested — factor their decision logic into a testable method and smoke-test the thin handler.
- Match existing style (`submit-job.php` for forms/handlers, `class-remjobs-payment-settings.php` for admin pages). Commit per task on `feature/paid-featured-listings`; `git add` only explicit files (never `-A`/`docs/`/`.superpowers/`); messages end with `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.
- Plugin is ACTIVE/LIVE — after wiring (Task 5) homepage must be `200` with no "critical error", or STOP (BLOCKED).

## File Structure
| File | Responsibility |
|---|---|
| `includes/payments/class-remjobs-payment-ui.php` *(create)* | all UI hooks: handlers, My Jobs shortcode, return notices, admin Orders page, job columns, `account_url` filter |
| `includes/payments/class-remjobs-checkout.php` *(modify)* | make the return/cancel base URL filterable: `apply_filters('remjobs_account_url', home_url('/'))` |
| `includes/payments/class-remjobs-payment-settings.php` *(modify)* | add a `my_jobs_page_id` field (int) to defaults/sanitize/page |
| `includes/payments/class-remjobs-payments.php` *(modify)* | instantiate `Remjobs_Payment_Ui`; expose `$GLOBALS['remjobs_payments']=$this` + a `remjobs_payments()` accessor |
| `includes/blocks/submit-job/submit-job.php` *(modify)* | add plan + gateway fields; route paid submissions to checkout |
| `tests/wpcli/test-ui-*.php` *(create)* | per task below |

---

### Task 1: `Remjobs_Payment_Ui` scaffold + handlers + account_url filter + accessor

**Files:** Create `class-remjobs-payment-ui.php`; modify `class-remjobs-checkout.php`, `class-remjobs-payments.php`; Test `tests/wpcli/test-ui-core.php`.

**Class:**
```php
class Remjobs_Payment_Ui
{
    private $checkout; private $gateways; private $featured;
    public function __construct(Remjobs_Checkout $checkout, array $gateways, Remjobs_Featured $featured) {...}

    public function register(): void
    {
        add_action('admin_post_remjobs_start_checkout', array($this, 'handle_start_checkout'));
        add_action('admin_post_remjobs_cancel_subscription', array($this, 'handle_cancel_subscription'));
        add_shortcode('remjobs_my_jobs', array($this, 'render_my_jobs'));
        add_filter('remjobs_account_url', array($this, 'my_jobs_url'));
        add_action('admin_menu', array($this, 'add_orders_page'));
        add_filter('manage_remjobs_jobs_posts_columns', array($this, 'add_job_columns'));
        add_action('manage_remjobs_jobs_posts_custom_column', array($this, 'render_job_column'), 10, 2);
    }

    public function my_jobs_url(string $url): string
    {
        $id = (int) Remjobs_Payment_Settings::get('my_jobs_page_id', 0);
        return $id > 0 ? (string) get_permalink($id) : $url;
    }

    /** enabled AND configured gateways, as id=>label */
    public function enabled_gateway_choices(): array
    {
        $enabled = (array) Remjobs_Payment_Settings::get('enabled_gateways', array());
        $labels = array('stripe' => __('Credit card (Stripe)', 'remote-jobs'), 'paypal' => __('PayPal', 'remote-jobs'));
        $out = array();
        foreach ($enabled as $id) {
            if (isset($this->gateways[$id]) && $this->gateways[$id]->is_configured()) { $out[$id] = $labels[$id] ?? $id; }
        }
        return $out;
    }

    public function handle_start_checkout(): void
    {
        if (!is_user_logged_in() || !check_admin_referer('remjobs_start_checkout')) { wp_die(esc_html__('Security check failed.', 'remote-jobs')); }
        $job_id  = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
        $plan    = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '';
        $gateway = isset($_POST['gateway']) ? sanitize_text_field(wp_unslash($_POST['gateway'])) : '';
        try {
            $url = $this->checkout->start(get_current_user_id(), $job_id, $plan, $gateway);
            wp_redirect($url); exit;
        } catch (Exception $e) {
            set_transient('remjobs_ui_error_' . get_current_user_id(), $e->getMessage(), 60);
            wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
        }
    }

    public function handle_cancel_subscription(): void
    {
        if (!is_user_logged_in() || !check_admin_referer('remjobs_cancel_subscription')) { wp_die(esc_html__('Security check failed.', 'remote-jobs')); }
        $sub_id = isset($_POST['subscription_id']) ? absint($_POST['subscription_id']) : 0;
        $sub = Remjobs_Subscriptions::get($sub_id);
        if ($sub && (int) $sub['user_id'] === get_current_user_id()) {
            $gid = (string) $sub['gateway'];
            if (isset($this->gateways[$gid])) { $this->gateways[$gid]->cancel_subscription((string) $sub['gateway_subscription_id']); }
            Remjobs_Subscriptions::set_status($sub_id, 'canceled');
        }
        set_transient('remjobs_ui_notice_' . get_current_user_id(), __('Subscription canceled.', 'remote-jobs'), 60);
        wp_safe_redirect(wp_get_referer() ?: home_url('/')); exit;
    }
    // render_my_jobs/add_orders_page/add_job_columns/render_job_column -> Tasks 3-4.
}
```
- In `Remjobs_Checkout`, change the return/cancel base URL to `apply_filters('remjobs_account_url', home_url('/'))` (find where it builds the return URL; keep `order_key` + nonce query args).
- In `Remjobs_Payments`: `require_once` + instantiate `$this->ui = new Remjobs_Payment_Ui($this->checkout, $this->gateways, $this->featured);` and call `$this->ui->register();` in `init()`. Add at top of `init()`: `$GLOBALS['remjobs_payments'] = $this;`. Add a plain function at file end: `function remjobs_payments() { return isset($GLOBALS['remjobs_payments']) ? $GLOBALS['remjobs_payments'] : null; }` and a `public function checkout()` accessor already exists from 3a.

**Test (`test-ui-core.php`):**
```php
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Payment_Ui'), 'ui class loaded');
// enabled_gateway_choices respects settings
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
  'enabled_gateways'=>array('stripe'),'stripe_secret_key'=>'sk','stripe_publishable_key'=>'pk','paypal_client_id'=>'','paypal_secret'=>'')));
$ref = new ReflectionClass('Remjobs_Payment_Ui'); // or build via orchestrator accessor if exposed
// Simplest: construct with the orchestrator's pieces is private; instead assert the shortcode + filter are registered:
$p->init();
remjobs_t_assert(shortcode_exists('remjobs_my_jobs'), 'my jobs shortcode registered');
remjobs_t_assert(has_filter('remjobs_account_url'), 'account_url filter registered');
// account_url points at My Jobs page when set
$pg = wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'My Jobs'));
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('my_jobs_page_id'=>$pg)));
remjobs_t_assert(apply_filters('remjobs_account_url', home_url('/')) === get_permalink($pg), 'account_url -> my jobs page');
wp_delete_post($pg, true); update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
```
> If exercising `enabled_gateway_choices()` directly is awkward (private construction), assert it via a tiny public path or skip it here and cover it in Task 2's submit-form test. Do NOT leave it untested across the whole phase.

**Steps:** write test → red → implement → green → commit `feat: add payment UI controller, handlers, and account URL filter`.

---

### Task 2: Submit Job integration (plan + gateway chooser, paid routing)

**Files:** Modify `includes/blocks/submit-job/submit-job.php`; Test `tests/wpcli/test-ui-submit.php`.

**Form:** in the submit form, add a **Listing type** select — `free` (default, "Free — reviewed before publishing"), `single` ("Featured — one-time"), `monthly` ("Featured — monthly") — the paid options labeled with their price from `Remjobs_Plans`. Add a **Payment method** select populated from `remjobs_payments()->ui_gateway_choices()` (expose a public `ui_gateway_choices()` on the orchestrator delegating to the UI's `enabled_gateway_choices()`), shown only when a paid plan is picked (a tiny inline script toggling visibility is fine; no build step). Only render paid options if `enabled_gateway_choices()` is non-empty.

**Handler (`remjobs_handle_job_submission`):** after the job is inserted, factor the post-insert routing into a testable function:
```php
function remjobs_route_submitted_job(int $job_id, string $plan, string $gateway, int $user_id): ?string
{
    if ($plan === '' || $plan === 'free' || !Remjobs_Plans::exists($plan)) {
        return null; // free: leave as 'pending' (existing behavior)
    }
    // Paid: hold as draft awaiting payment, then start checkout.
    wp_update_post(array('ID' => $job_id, 'post_status' => 'draft'));
    update_post_meta($job_id, '_remjobs_payment_status', 'awaiting');
    $checkout = remjobs_payments() ? remjobs_payments()->checkout() : null;
    if (!$checkout) { return null; }
    return $checkout->start($user_id, $job_id, $plan, $gateway); // throws on validation error
}
```
In the handler: read `plan`/`gateway` from `$_POST` (sanitized); the job is currently inserted as `pending` — for a paid plan, call `remjobs_route_submitted_job`; on a returned URL, `wp_redirect($url); exit;`. On a thrown exception, store the error transient and redirect back (do not publish). Free plan → unchanged success redirect.

**Test (`test-ui-submit.php`)** — exercise `remjobs_route_submitted_job` with a stub checkout:
```php
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/blocks/submit-job/submit-job.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
(new Remjobs_Payments())->init(); // registers $GLOBALS['remjobs_payments']
// free plan: job stays pending, returns null
$j1 = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'pending','post_title'=>'free job','post_author'=>1));
remjobs_t_assert(remjobs_route_submitted_job($j1,'free','',1) === null, 'free plan not routed');
remjobs_t_assert(get_post_status($j1) === 'pending', 'free stays pending');
// paid plan: job becomes draft+awaiting (checkout may throw if gateway unconfigured — catch and assert state)
$j2 = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'pending','post_title'=>'paid job','post_author'=>1));
try { remjobs_route_submitted_job($j2,'single','stripe',1); } catch (Exception $e) {}
remjobs_t_assert(get_post_status($j2) === 'draft', 'paid job held as draft');
remjobs_t_assert(get_post_meta($j2,'_remjobs_payment_status',true) === 'awaiting', 'paid job awaiting');
wp_delete_post($j1,true); wp_delete_post($j2,true);
remjobs_t_done();
```

**Steps:** write test → red → implement (form fields + `ui_gateway_choices()` accessor + `remjobs_route_submitted_job` + handler wiring) → green → commit `feat: route paid job submissions through checkout`.

---

### Task 3: My Jobs shortcode + return/cancel notices

**Files:** Modify `class-remjobs-payment-ui.php`; Test `tests/wpcli/test-ui-myjobs.php`.

**`render_my_jobs($atts): string`** (shortcode): if not logged in, return a login prompt. Else query the current user's `remjobs_jobs` (`author`=current user, all statuses, `posts_per_page`=50). Build an escaped HTML table: Title, Status (map `draft`+awaiting→"Awaiting payment"; `publish`+featured→"Featured until {expires}"; else the post status), and an Action cell:
- not featured & `publish`/`pending`/`draft` → a **Feature** form (plan select from `Remjobs_Plans`, gateway select from `enabled_gateway_choices()`, hidden `job_id`, nonce `remjobs_start_checkout`, action `remjobs_start_checkout`, submit to `admin_url('admin-post.php')`).
- featured via subscription (`_remjobs_feature_subscription_id`) → show renewal date (`_remjobs_featured_expires`) + a **Cancel subscription** form (hidden `subscription_id`, nonce `remjobs_cancel_subscription`).
Prepend any `remjobs_ui_error_{uid}` / `remjobs_ui_notice_{uid}` transient (then delete it), and a return/cancel notice when `?remjobs_return=<key>`/`?remjobs_cancel=1` is present: verify the return nonce (`remjobs_return_{key}`), load the order by key, and show "Payment received — your listing is being published." when `status==='paid'`, else "We're confirming your payment…".

**Test (`test-ui-myjobs.php`):** set current user to 1; create one featured publish job + one plain pending job for user 1; render the shortcode; assert the HTML contains both titles, the string "Featured", and a Feature `<form`/`remjobs_start_checkout` action and a nonce field. Then not-logged-in (`wp_set_current_user(0)`) → output contains a login prompt and no Feature form. Clean up.

**Steps:** write test → red → implement → green → commit `feat: add My Jobs dashboard shortcode with feature and cancel actions`.

---

### Task 4: Admin Orders list + job columns

**Files:** Modify `class-remjobs-payment-ui.php`; Test `tests/wpcli/test-ui-admin.php`.

- `add_orders_page()`: `add_submenu_page('edit.php?post_type=remjobs_jobs', __('Orders','remote-jobs'), __('Orders','remote-jobs'), 'manage_options', 'remjobs-orders', array($this,'render_orders_page'))`.
- `render_orders_page()`: `manage_options` guard; query the orders table (`SELECT * ... ORDER BY id DESC LIMIT 200`) and print an escaped `<table class="wp-list-table widefat fixed striped">` with columns: ID, Date, User, Job, Plan, Gateway, Amount+Currency, Status. Use `get_the_title($job_id)` / `get_userdata`.
- `add_job_columns($cols)`: insert `remjobs_featured` ("Featured"), `remjobs_expires` ("Feature expires"), `remjobs_payment` ("Payment") before the `date` column.
- `render_job_column($col, $post_id)`: `remjobs_featured` → "Yes"/"—" from `_remjobs_featured`; `remjobs_expires` → formatted `_remjobs_featured_expires` or "—"; `remjobs_payment` → `_remjobs_payment_status` or "—". Escape everything.

**Test (`test-ui-admin.php`):**
```php
// columns
$cols = array('title'=>'Title','date'=>'Date');
$out = (/* ui instance via orchestrator or reflection */)->add_job_columns($cols);
remjobs_t_assert(isset($out['remjobs_featured'],$out['remjobs_expires'],$out['remjobs_payment']), 'job columns added');
// column value
$job = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'col job','post_author'=>1));
update_post_meta($job,'_remjobs_featured','1');
ob_start(); $ui->render_job_column('remjobs_featured', $job); $v = ob_get_clean();
remjobs_t_assert(strpos($v,'Yes') !== false, 'featured column shows Yes');
wp_delete_post($job,true);
```
> To get a `Remjobs_Payment_Ui` instance in the test, add a public accessor on the orchestrator (`public function ui()`) mirroring `checkout()`, OR construct the UI directly with the orchestrator's gateways. Pick one and keep it consistent.

**Steps:** write test → red → implement → green → commit `feat: add admin orders list and job featured columns`.

---

### Task 5: `my_jobs_page_id` setting + wiring + regression + live smoke

**Files:** Modify `class-remjobs-payment-settings.php`; Test `tests/wpcli/test-ui-wiring.php`.
- Add `my_jobs_page_id` (int, default 0) to `defaults()`, `sanitize()` (`absint`), and a field on the settings page (a numeric input or a `wp_dropdown_pages` is fine).
- Verify the orchestrator registers the UI (shortcode exists, admin-post actions registered).
- Create a page with `[remjobs_my_jobs]`, set `my_jobs_page_id`, and `curl` it (logged-out) → `200`, contains the login prompt (confirms the shortcode renders on a real request).
- FULL regression: every `tests/wpcli/test-*.php` → `RESULT: PASS`. Homepage `200`, no "critical error". Submit-job page (if one exists) still renders.

**Steps:** write test → red → implement setting + confirm wiring → green → regression + smoke → commit `feat: add My Jobs page setting and finalize payment UI wiring`.

## Self-Review (plan vs spec §5.3/§7)
- Submit-time Free-vs-Feature choice + paid routing to checkout + draft/awaiting hold → Task 2. ✅
- My Jobs dashboard: feature existing jobs, cancel subscription, renewal date, return notices → Tasks 1,3. ✅
- Admin Orders list + job columns → Task 4. ✅
- Security (nonce, login, ownership, escaping) across handlers/shortcode → Tasks 1-4. ✅
- `account_url` → My Jobs page → Tasks 1,5. ✅
- Payment still confirmed server-side by webhook (unchanged); this phase only initiates checkout and displays state.
