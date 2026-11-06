# Paid Featured Listings — Phase 1: Foundation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the data + configuration foundation for paid featured listings: database tables for orders and subscriptions, a settings store with an admin page, a central plans/pricing resolver, and typed CRUD stores — all wired into the plugin bootstrap.

**Architecture:** Approach A from the spec. Plans are configuration (two fixed plans) read from a single option; orders and subscriptions live in two custom tables accessed through dedicated store classes; a `Remjobs_Payments` orchestrator loads everything and self-heals the schema. No gateway or UI-facing job code in this phase.

**Tech Stack:** PHP 7.4+ (plugin floor), WordPress 5.8+, `$wpdb` + `dbDelta` over the SQLite integration, WordPress Settings API, WP-CLI (`studio wp`) for the test harness.

**Spec:** `docs/superpowers/specs/2026-11-06-remote-jobs-payments-design.md`

## Global Constraints

- PHP floor **7.4**; WordPress floor **5.8** (from `remote-jobs.php`). No PHP 8-only syntax.
- Text domain: **`remote-jobs`** on every user-facing string.
- Class prefix **`Remjobs_`**, function/meta/option prefix **`remjobs_`**; one class per file; files begin with the `if (!defined('ABSPATH')) exit;` guard. New class files use `declare(strict_types=1);` (matches `class-remjobs-job.php`).
- **No Composer / no bundled SDKs.**
- All table access through `$wpdb->prepare`; sanitize input, escape output.
- All stored timestamps are **UTC** (`gmdate('Y-m-d H:i:s')`).
- Secrets read through `Remjobs_Payment_Settings::get()`, which lets `wp-config.php` constants override stored options.
- Run every `wp` command as **`studio wp`** (this is a WordPress Studio site).
- Commit after each task with a `feat:`/`test:`/`chore:` message.

## File Structure

| File | Responsibility |
|---|---|
| `includes/payments/class-remjobs-payments.php` | Orchestrator: load deps, self-heal schema on version bump, register settings page. Grows in later phases. |
| `includes/payments/class-remjobs-plans.php` | Resolve the two plans from settings; amount/zero-decimal math. |
| `includes/payments/class-remjobs-orders.php` | Orders table CRUD + state transitions + idempotency. |
| `includes/payments/class-remjobs-subscriptions.php` | Subscriptions table CRUD + upsert by gateway id. |
| `includes/payments/class-remjobs-payment-settings.php` | Settings get/sanitize (+ constant override) and the admin settings page. |
| `includes/class-remjobs-activator.php` *(modify)* | Create tables via `dbDelta`, store schema version, seed default settings. |
| `includes/class-remjobs-core.php` *(modify)* | Require + instantiate `Remjobs_Payments`. |
| `remote-jobs.php` *(modify)* | Define `REMJOBS_DB_VERSION`. |
| `tests/wpcli/_assert.php` | Shared assert helpers for the harness. |
| `tests/wpcli/*.php` | One assertion script per task, run via `studio wp eval-file`. |

**DB version constant** drives the self-heal: bumping `REMJOBS_DB_VERSION` makes the orchestrator re-run `dbDelta` on `plugins_loaded` for installs updated without reactivation.

---

### Task 0: Test harness helper

**Files:**
- Create: `tests/wpcli/_assert.php`

**Interfaces:**
- Produces: global functions `remjobs_t_assert(bool $cond, string $label): void`, `remjobs_t_done(): void` for use by every later test script (they `require __DIR__ . '/_assert.php';`).

- [ ] **Step 1: Write the harness helper**

```php
<?php
// tests/wpcli/_assert.php — shared assertions for `studio wp eval-file` scripts.
// Usage: require __DIR__ . '/_assert.php'; remjobs_t_assert($cond, 'label'); ... remjobs_t_done();

$GLOBALS['remjobs_t_fail'] = 0;

function remjobs_t_assert($cond, $label)
{
    if ($cond) {
        echo "PASS: {$label}\n";
    } else {
        $GLOBALS['remjobs_t_fail']++;
        echo "FAIL: {$label}\n";
    }
}

function remjobs_t_done()
{
    if ($GLOBALS['remjobs_t_fail'] > 0) {
        echo "RESULT: FAIL ({$GLOBALS['remjobs_t_fail']} failed)\n";
    } else {
        echo "RESULT: PASS\n";
    }
}
```

- [ ] **Step 2: Verify it loads**

Run: `studio wp eval-file tests/wpcli/_assert.php`
Expected: no output and no PHP error (defining functions only). Exit 0.

- [ ] **Step 3: Commit**

```bash
git add tests/wpcli/_assert.php
git commit -m "test: add wp-cli assertion harness for payments"
```

---

### Task 1: Database schema + activator

**Files:**
- Modify: `remote-jobs.php` (add `REMJOBS_DB_VERSION`)
- Modify: `includes/class-remjobs-activator.php`
- Test: `tests/wpcli/test-schema.php`

**Interfaces:**
- Produces: tables `{$wpdb->prefix}remjobs_orders` and `{$wpdb->prefix}remjobs_subscriptions` (columns per spec §4.1/§4.2); option `remjobs_db_version`; static method `Remjobs_Activator::create_tables(): void`.

- [ ] **Step 1: Define the DB version constant**

In `remote-jobs.php`, after the `REMJOBS_PLUGIN_URL` define:

```php
define('REMJOBS_DB_VERSION', '1');
```

- [ ] **Step 2: Write the failing test**

```php
<?php
// tests/wpcli/test-schema.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';

global $wpdb;
Remjobs_Activator::create_tables();

$orders = $wpdb->prefix . 'remjobs_orders';
$subs   = $wpdb->prefix . 'remjobs_subscriptions';

$have_orders = $wpdb->get_var($wpdb->prepare('SELECT name FROM sqlite_master WHERE type=%s AND name=%s', 'table', $orders));
$have_subs   = $wpdb->get_var($wpdb->prepare('SELECT name FROM sqlite_master WHERE type=%s AND name=%s', 'table', $subs));

remjobs_t_assert($have_orders === $orders, 'orders table exists');
remjobs_t_assert($have_subs === $subs, 'subscriptions table exists');

// Insert + read back an order row to prove columns exist.
$ok = $wpdb->insert($orders, array(
    'order_key' => 'k_test_1', 'user_id' => 1, 'plan' => 'single', 'gateway' => 'stripe',
    'amount' => '49.00', 'currency' => 'USD', 'status' => 'pending',
    'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
));
remjobs_t_assert($ok === 1, 'can insert an order row');
remjobs_t_assert(get_option('remjobs_db_version') === '1', 'db version stored');

$wpdb->delete($orders, array('order_key' => 'k_test_1'));
remjobs_t_done();
```

- [ ] **Step 3: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-schema.php`
Expected: FAIL — `Remjobs_Activator::create_tables()` does not exist yet (fatal) or tables missing.

- [ ] **Step 4: Implement `create_tables()` in the activator**

Replace the body of `includes/class-remjobs-activator.php` so `activate()` also creates tables, and add `create_tables()`:

```php
    public static function activate()
    {
        add_option('remjobs_settings', array(
            'jobs_per_page' => 10,
            'enable_application_tracking' => true,
            'notification_email' => get_option('admin_email'),
        ));

        self::create_tables();
        self::seed_payment_settings();

        add_action('init', array(__CLASS__, 'flush_rewrite_rules_on_activation'), 20);
    }

    public static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $orders = $wpdb->prefix . 'remjobs_orders';
        $subs   = $wpdb->prefix . 'remjobs_subscriptions';

        $sql_orders = "CREATE TABLE {$orders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_key VARCHAR(32) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            job_id BIGINT UNSIGNED NULL,
            plan VARCHAR(20) NOT NULL,
            gateway VARCHAR(20) NOT NULL,
            gateway_order_id VARCHAR(255) NULL,
            gateway_event_id VARCHAR(255) NULL,
            subscription_id BIGINT UNSIGNED NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_key (order_key),
            KEY user_id (user_id),
            KEY job_id (job_id),
            KEY gateway_order_id (gateway_order_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_subs = "CREATE TABLE {$subs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            scope VARCHAR(10) NOT NULL DEFAULT 'job',
            job_id BIGINT UNSIGNED NULL,
            gateway VARCHAR(20) NOT NULL,
            gateway_subscription_id VARCHAR(255) NOT NULL,
            gateway_customer_id VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            current_period_end DATETIME NULL,
            cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY gateway_subscription_id (gateway_subscription_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql_orders);
        dbDelta($sql_subs);
        update_option('remjobs_db_version', REMJOBS_DB_VERSION);
    }

    public static function seed_payment_settings(): void
    {
        if (false === get_option('remjobs_payment_settings')) {
            add_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
        }
    }
```

Add `require_once` for the settings class at the top of `activate()`/`seed_payment_settings()` guard — since the activator runs standalone, require it:

```php
    // at top of seed_payment_settings(), before use:
    require_once REMJOBS_PLUGIN_PATH . 'includes/payments/class-remjobs-payment-settings.php';
```

> Note: `seed_payment_settings()` depends on Task 2's `Remjobs_Payment_Settings::defaults()`. If executing strictly in order, temporarily inline the defaults array here and replace with the call after Task 2, or implement Task 2 first. The test for this task does not exercise `seed_payment_settings()`.

- [ ] **Step 5: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-schema.php`
Expected: `RESULT: PASS`.

- [ ] **Step 6: Commit**

```bash
git add remote-jobs.php includes/class-remjobs-activator.php tests/wpcli/test-schema.php
git commit -m "feat: create orders/subscriptions tables in activator"
```

---

### Task 2: Settings store (get / defaults / sanitize / constant override)

**Files:**
- Create: `includes/payments/class-remjobs-payment-settings.php`
- Test: `tests/wpcli/test-settings.php`

**Interfaces:**
- Produces:
  - `Remjobs_Payment_Settings::defaults(): array`
  - `Remjobs_Payment_Settings::get(string $key, $fallback = null)` — returns the setting, with `wp-config.php` constant override for secret keys
  - `Remjobs_Payment_Settings::all(): array`
  - `Remjobs_Payment_Settings::sanitize(array $input): array`
  - constant map: `stripe_secret_key→REMJOBS_STRIPE_SECRET`, `stripe_webhook_secret→REMJOBS_STRIPE_WEBHOOK_SECRET`, `paypal_secret→REMJOBS_PAYPAL_SECRET`, `paypal_client_id→REMJOBS_PAYPAL_CLIENT_ID`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-settings.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';

$d = Remjobs_Payment_Settings::defaults();
remjobs_t_assert($d['currency'] === 'USD', 'default currency USD');
remjobs_t_assert($d['single_price'] === '49.00', 'default single price');
remjobs_t_assert($d['single_duration_days'] === 30, 'default single duration');
remjobs_t_assert($d['monthly_scope'] === 'job', 'default monthly scope job');
remjobs_t_assert($d['expiry_behavior'] === 'unfeature', 'default expiry behavior');
remjobs_t_assert($d['mode'] === 'sandbox', 'default mode sandbox');

// Sanitize coerces/clamps.
$clean = Remjobs_Payment_Settings::sanitize(array(
    'currency' => 'usd', 'single_price' => '49', 'single_duration_days' => '45',
    'monthly_scope' => 'bogus', 'expiry_behavior' => 'unpublish', 'mode' => 'live',
    'monthly_account_cap' => '-3',
));
remjobs_t_assert($clean['currency'] === 'USD', 'currency upcased');
remjobs_t_assert($clean['single_price'] === '49.00', 'price normalized to 2dp');
remjobs_t_assert($clean['single_duration_days'] === 45, 'duration cast int');
remjobs_t_assert($clean['monthly_scope'] === 'job', 'invalid scope falls back');
remjobs_t_assert($clean['expiry_behavior'] === 'unpublish', 'valid expiry kept');
remjobs_t_assert($clean['monthly_account_cap'] === 0, 'negative cap clamped to 0');

// Constant override for secrets.
if (!defined('REMJOBS_STRIPE_SECRET')) { define('REMJOBS_STRIPE_SECRET', 'sk_from_const'); }
update_option('remjobs_payment_settings', array_merge($d, array('stripe_secret_key' => 'sk_from_db')));
remjobs_t_assert(Remjobs_Payment_Settings::get('stripe_secret_key') === 'sk_from_const', 'constant overrides db secret');
remjobs_t_assert(Remjobs_Payment_Settings::get('currency') === 'USD', 'non-secret read from db');

remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-settings.php`
Expected: FAIL — class not found (fatal).

- [ ] **Step 3: Implement the settings class**

```php
<?php
/**
 * Payment settings store + admin page.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Payment_Settings
{
    const OPTION = 'remjobs_payment_settings';

    /** setting key => wp-config constant that overrides it */
    const CONSTANTS = array(
        'stripe_secret_key'     => 'REMJOBS_STRIPE_SECRET',
        'stripe_webhook_secret' => 'REMJOBS_STRIPE_WEBHOOK_SECRET',
        'paypal_client_id'      => 'REMJOBS_PAYPAL_CLIENT_ID',
        'paypal_secret'         => 'REMJOBS_PAYPAL_SECRET',
    );

    public static function defaults(): array
    {
        return array(
            'enabled_gateways'        => array('stripe', 'paypal'),
            'mode'                    => 'sandbox',
            'currency'                => 'USD',
            'stripe_publishable_key'  => '',
            'stripe_secret_key'       => '',
            'stripe_webhook_secret'   => '',
            'stripe_price_id_monthly' => '',
            'paypal_client_id'        => '',
            'paypal_secret'           => '',
            'paypal_webhook_id'       => '',
            'paypal_plan_id_monthly'  => '',
            'single_price'            => '49.00',
            'single_duration_days'    => 30,
            'monthly_price'           => '99.00',
            'monthly_scope'           => 'job',
            'monthly_account_cap'     => 0,
            'expiry_behavior'         => 'unfeature',
        );
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, array());
        return array_merge(self::defaults(), is_array($stored) ? $stored : array());
    }

    /**
     * @param string $key
     * @param mixed  $fallback
     * @return mixed
     */
    public static function get(string $key, $fallback = null)
    {
        if (isset(self::CONSTANTS[$key]) && defined(self::CONSTANTS[$key])) {
            return constant(self::CONSTANTS[$key]);
        }
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $fallback;
    }

    public static function sanitize(array $input): array
    {
        $d = self::defaults();
        $out = $d;

        $out['mode'] = (isset($input['mode']) && $input['mode'] === 'live') ? 'live' : 'sandbox';
        $out['currency'] = isset($input['currency'])
            ? strtoupper(substr(sanitize_text_field($input['currency']), 0, 3)) : $d['currency'];

        $gateways = isset($input['enabled_gateways']) && is_array($input['enabled_gateways'])
            ? array_values(array_intersect(array('stripe', 'paypal'), array_map('sanitize_text_field', $input['enabled_gateways'])))
            : array();
        $out['enabled_gateways'] = $gateways;

        foreach (array('stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'stripe_price_id_monthly',
                       'paypal_client_id', 'paypal_secret', 'paypal_webhook_id', 'paypal_plan_id_monthly') as $k) {
            $out[$k] = isset($input[$k]) ? sanitize_text_field($input[$k]) : '';
        }

        $out['single_price']  = number_format((float) ($input['single_price'] ?? $d['single_price']), 2, '.', '');
        $out['monthly_price'] = number_format((float) ($input['monthly_price'] ?? $d['monthly_price']), 2, '.', '');
        $out['single_duration_days'] = max(1, (int) ($input['single_duration_days'] ?? $d['single_duration_days']));
        $out['monthly_account_cap']  = max(0, (int) ($input['monthly_account_cap'] ?? $d['monthly_account_cap']));
        $out['monthly_scope']    = (isset($input['monthly_scope']) && $input['monthly_scope'] === 'account') ? 'account' : 'job';
        $out['expiry_behavior']  = (isset($input['expiry_behavior']) && $input['expiry_behavior'] === 'unpublish') ? 'unpublish' : 'unfeature';

        return $out;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-settings.php`
Expected: `RESULT: PASS`.

- [ ] **Step 5: Replace the activator's inlined defaults (if inlined in Task 1)**

Ensure `Remjobs_Activator::seed_payment_settings()` calls `Remjobs_Payment_Settings::defaults()` (require the file first). Re-run `studio wp eval-file tests/wpcli/test-schema.php` to confirm still green.

- [ ] **Step 6: Commit**

```bash
git add includes/payments/class-remjobs-payment-settings.php includes/class-remjobs-activator.php tests/wpcli/test-settings.php
git commit -m "feat: add payment settings store with constant override"
```

---

### Task 3: Settings admin page

**Files:**
- Modify: `includes/payments/class-remjobs-payment-settings.php` (add page registration + render)
- Test: `tests/wpcli/test-settings-page.php`

**Interfaces:**
- Consumes: `Remjobs_Payment_Settings::all()`, `::sanitize()`.
- Produces:
  - instance methods `register(): void` (hooks `admin_menu` + `admin_init`), `add_menu(): void`, `register_settings(): void`, `render_page(): void`
  - submenu page under the Jobs CPT menu (`edit.php?post_type=remjobs_jobs`), slug `remjobs-payments`, registered setting group `remjobs_payments` using `Remjobs_Payment_Settings::OPTION` with `sanitize` callback.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-settings-page.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';

$s = new Remjobs_Payment_Settings();
remjobs_t_assert(method_exists($s, 'register'), 'has register()');
remjobs_t_assert(method_exists($s, 'render_page'), 'has render_page()');

$s->register();
remjobs_t_assert(has_action('admin_menu', array($s, 'add_menu')) !== false, 'admin_menu hooked');
remjobs_t_assert(has_action('admin_init', array($s, 'register_settings')) !== false, 'admin_init hooked');

// register_settings must whitelist the option so saving is allowed.
$s->register_settings();
global $wp_registered_settings, $allowed_options, $new_whitelist_options;
$group = isset($allowed_options) ? $allowed_options : (isset($new_whitelist_options) ? $new_whitelist_options : array());
remjobs_t_assert(isset($group['remjobs_payments']), 'settings group registered');

remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-settings-page.php`
Expected: FAIL — `register()` undefined.

- [ ] **Step 3: Implement the admin page methods**

Append inside `class Remjobs_Payment_Settings` (before the closing brace):

```php
    public function register(): void
    {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_menu(): void
    {
        add_submenu_page(
            'edit.php?post_type=remjobs_jobs',
            __('Payments', 'remote-jobs'),
            __('Payments', 'remote-jobs'),
            'manage_options',
            'remjobs-payments',
            array($this, 'render_page')
        );
    }

    public function register_settings(): void
    {
        register_setting('remjobs_payments', self::OPTION, array(
            'type'              => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize'),
            'default'           => self::defaults(),
        ));
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $s = self::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Remote Jobs — Payments', 'remote-jobs'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('remjobs_payments'); ?>
                <table class="form-table" role="presentation">
                    <?php
                    $this->text_row('currency', __('Currency (ISO 4217)', 'remote-jobs'), $s);
                    $this->select_row('mode', __('Mode', 'remote-jobs'), $s, array('sandbox' => 'Sandbox', 'live' => 'Live'));
                    $this->text_row('single_price', __('Single plan price', 'remote-jobs'), $s);
                    $this->text_row('single_duration_days', __('Single plan featured days', 'remote-jobs'), $s);
                    $this->text_row('monthly_price', __('Monthly plan price', 'remote-jobs'), $s);
                    $this->select_row('monthly_scope', __('Monthly scope', 'remote-jobs'), $s, array('job' => 'Per job', 'account' => 'Account-wide'));
                    $this->text_row('monthly_account_cap', __('Account cap (0 = unlimited)', 'remote-jobs'), $s);
                    $this->select_row('expiry_behavior', __('On expiry', 'remote-jobs'), $s, array('unfeature' => 'Unfeature (keep published)', 'unpublish' => 'Unpublish'));
                    $this->text_row('stripe_publishable_key', __('Stripe publishable key', 'remote-jobs'), $s);
                    $this->text_row('stripe_secret_key', __('Stripe secret key', 'remote-jobs'), $s);
                    $this->text_row('stripe_webhook_secret', __('Stripe webhook secret', 'remote-jobs'), $s);
                    $this->text_row('paypal_client_id', __('PayPal client ID', 'remote-jobs'), $s);
                    $this->text_row('paypal_secret', __('PayPal secret', 'remote-jobs'), $s);
                    $this->text_row('paypal_webhook_id', __('PayPal webhook ID', 'remote-jobs'), $s);
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function text_row(string $key, string $label, array $s): void
    {
        $val = isset($s[$key]) ? $s[$key] : '';
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input type="text" id="%1$s" name="%3$s[%1$s]" value="%4$s" class="regular-text" /></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr(self::OPTION),
            esc_attr((string) $val)
        );
    }

    private function select_row(string $key, string $label, array $s, array $choices): void
    {
        $val = isset($s[$key]) ? $s[$key] : '';
        $opts = '';
        foreach ($choices as $ck => $cl) {
            $opts .= sprintf('<option value="%s"%s>%s</option>', esc_attr($ck), selected($val, $ck, false), esc_html($cl));
        }
        printf(
            '<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><select id="%1$s" name="%3$s[%1$s]">%4$s</select></td></tr>',
            esc_attr($key),
            esc_html($label),
            esc_attr(self::OPTION),
            $opts // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_attr/esc_html above
        );
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-settings-page.php`
Expected: `RESULT: PASS`.

- [ ] **Step 5: Commit**

```bash
git add includes/payments/class-remjobs-payment-settings.php tests/wpcli/test-settings-page.php
git commit -m "feat: add payments admin settings page"
```

---

### Task 4: Plans resolver

**Files:**
- Create: `includes/payments/class-remjobs-plans.php`
- Test: `tests/wpcli/test-plans.php`

**Interfaces:**
- Consumes: `Remjobs_Payment_Settings::get()`.
- Produces:
  - `Remjobs_Plans::ids(): array` → `['single','monthly']`
  - `Remjobs_Plans::exists(string $plan): bool`
  - `Remjobs_Plans::get(string $plan): ?array` → `['id','type','price','currency','duration_days'|null,'scope'|null,'cap'|null]` where `type` is `one_time`|`subscription`
  - `Remjobs_Plans::amount_minor(string $plan): int` → integer in the currency's minor unit (cents), zero-decimal-aware
  - `Remjobs_Plans::ZERO_DECIMAL` const array (e.g. `JPY`, `KRW`, `VND`, …)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-plans.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-plans.php';

update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency' => 'USD', 'single_price' => '49.00', 'single_duration_days' => 30,
    'monthly_price' => '99.00', 'monthly_scope' => 'job',
)));

remjobs_t_assert(Remjobs_Plans::ids() === array('single', 'monthly'), 'plan ids');
remjobs_t_assert(Remjobs_Plans::exists('single') === true, 'single exists');
remjobs_t_assert(Remjobs_Plans::exists('nope') === false, 'unknown plan missing');

$single = Remjobs_Plans::get('single');
remjobs_t_assert($single['type'] === 'one_time', 'single is one_time');
remjobs_t_assert($single['duration_days'] === 30, 'single duration');
remjobs_t_assert(Remjobs_Plans::amount_minor('single') === 4900, 'single amount in cents');

$monthly = Remjobs_Plans::get('monthly');
remjobs_t_assert($monthly['type'] === 'subscription', 'monthly is subscription');
remjobs_t_assert($monthly['scope'] === 'job', 'monthly scope');
remjobs_t_assert(Remjobs_Plans::amount_minor('monthly') === 9900, 'monthly amount in cents');

// Zero-decimal currency: amount is the integer price, not ×100.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency' => 'JPY', 'single_price' => '5000.00',
)));
remjobs_t_assert(Remjobs_Plans::amount_minor('single') === 5000, 'zero-decimal amount not multiplied');

remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-plans.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the plans resolver**

```php
<?php
/**
 * Plan + pricing resolver. The plugin ships exactly two plans, configured via settings.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Plans
{
    const ZERO_DECIMAL = array('BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF');

    public static function ids(): array
    {
        return array('single', 'monthly');
    }

    public static function exists(string $plan): bool
    {
        return in_array($plan, self::ids(), true);
    }

    public static function get(string $plan): ?array
    {
        if (!self::exists($plan)) {
            return null;
        }
        $currency = (string) Remjobs_Payment_Settings::get('currency', 'USD');
        if ($plan === 'single') {
            return array(
                'id'            => 'single',
                'type'          => 'one_time',
                'price'         => (string) Remjobs_Payment_Settings::get('single_price', '49.00'),
                'currency'      => $currency,
                'duration_days' => (int) Remjobs_Payment_Settings::get('single_duration_days', 30),
                'scope'         => null,
                'cap'           => null,
            );
        }
        return array(
            'id'            => 'monthly',
            'type'          => 'subscription',
            'price'         => (string) Remjobs_Payment_Settings::get('monthly_price', '99.00'),
            'currency'      => $currency,
            'duration_days' => null,
            'scope'         => (string) Remjobs_Payment_Settings::get('monthly_scope', 'job'),
            'cap'           => (int) Remjobs_Payment_Settings::get('monthly_account_cap', 0),
        );
    }

    public static function amount_minor(string $plan): int
    {
        $p = self::get($plan);
        if (!$p) {
            return 0;
        }
        $price = (float) $p['price'];
        if (in_array(strtoupper($p['currency']), self::ZERO_DECIMAL, true)) {
            return (int) round($price);
        }
        return (int) round($price * 100);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-plans.php`
Expected: `RESULT: PASS`.

- [ ] **Step 5: Restore default settings and commit**

```bash
studio wp eval "update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());" --require=wp-content/plugins/remote-jobs/includes/payments/class-remjobs-payment-settings.php
git add includes/payments/class-remjobs-plans.php tests/wpcli/test-plans.php
git commit -m "feat: add plans/pricing resolver"
```

---

### Task 5: Orders store

**Files:**
- Create: `includes/payments/class-remjobs-orders.php`
- Test: `tests/wpcli/test-orders.php`

**Interfaces:**
- Produces (all static):
  - `Remjobs_Orders::create(array $data): int` — required keys `user_id, plan, gateway, amount, currency`; optional `job_id, subscription_id`. Generates `order_key`, sets `status='pending'`, timestamps. Returns insert id.
  - `Remjobs_Orders::get(int $id): ?array`
  - `Remjobs_Orders::get_by_key(string $key): ?array`
  - `Remjobs_Orders::get_by_gateway_order_id(string $gateway, string $gateway_order_id): ?array`
  - `Remjobs_Orders::update(int $id, array $fields): bool` (auto-sets `updated_at`)
  - `Remjobs_Orders::mark_paid(int $id, string $gateway_order_id = '', string $event_id = ''): bool` — idempotent: no-op returning `true` if already `paid`
  - `Remjobs_Orders::table(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-orders.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-orders.php';
Remjobs_Activator::create_tables();

$id = Remjobs_Orders::create(array(
    'user_id' => 7, 'job_id' => 123, 'plan' => 'single',
    'gateway' => 'stripe', 'amount' => '49.00', 'currency' => 'USD',
));
remjobs_t_assert($id > 0, 'create returns id');

$o = Remjobs_Orders::get($id);
remjobs_t_assert($o['status'] === 'pending', 'new order pending');
remjobs_t_assert(strlen($o['order_key']) >= 16, 'order_key generated');
remjobs_t_assert(Remjobs_Orders::get_by_key($o['order_key'])['id'] == $id, 'lookup by key');

remjobs_t_assert(Remjobs_Orders::mark_paid($id, 'cs_test_1', 'evt_1') === true, 'mark paid');
remjobs_t_assert(Remjobs_Orders::get($id)['status'] === 'paid', 'status is paid');
remjobs_t_assert(Remjobs_Orders::get_by_gateway_order_id('stripe', 'cs_test_1')['id'] == $id, 'lookup by gateway order id');

// Idempotent: second mark_paid is a no-op success.
remjobs_t_assert(Remjobs_Orders::mark_paid($id, 'cs_test_1', 'evt_1') === true, 'mark paid idempotent');

global $wpdb;
$wpdb->delete(Remjobs_Orders::table(), array('id' => $id));
remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-orders.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the orders store**

```php
<?php
/**
 * Orders table store.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Orders
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'remjobs_orders';
    }

    public static function create(array $data): int
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $row = array(
            'order_key'        => self::generate_key(),
            'user_id'          => (int) ($data['user_id'] ?? 0),
            'job_id'           => isset($data['job_id']) ? (int) $data['job_id'] : null,
            'plan'             => sanitize_text_field((string) ($data['plan'] ?? '')),
            'gateway'          => sanitize_text_field((string) ($data['gateway'] ?? '')),
            'gateway_order_id' => isset($data['gateway_order_id']) ? sanitize_text_field((string) $data['gateway_order_id']) : null,
            'subscription_id'  => isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            'amount'           => number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            'currency'         => strtoupper(substr((string) ($data['currency'] ?? 'USD'), 0, 3)),
            'status'           => 'pending',
            'created_at'       => $now,
            'updated_at'       => $now,
        );
        $wpdb->insert(self::table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_key(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE order_key = %s', $key), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_gateway_order_id(string $gateway, string $gateway_order_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_order_id = %s', $gateway, $gateway_order_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function update(int $id, array $fields): bool
    {
        global $wpdb;
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        return false !== $wpdb->update(self::table(), $fields, array('id' => $id));
    }

    public static function mark_paid(int $id, string $gateway_order_id = '', string $event_id = ''): bool
    {
        $order = self::get($id);
        if (!$order) {
            return false;
        }
        if ($order['status'] === 'paid') {
            return true; // idempotent
        }
        $fields = array('status' => 'paid');
        if ($gateway_order_id !== '') {
            $fields['gateway_order_id'] = $gateway_order_id;
        }
        if ($event_id !== '') {
            $fields['gateway_event_id'] = $event_id;
        }
        return self::update($id, $fields);
    }

    private static function generate_key(): string
    {
        return 'rjk_' . wp_generate_password(20, false, false);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-orders.php`
Expected: `RESULT: PASS`.

- [ ] **Step 5: Commit**

```bash
git add includes/payments/class-remjobs-orders.php tests/wpcli/test-orders.php
git commit -m "feat: add orders store with idempotent mark_paid"
```

---

### Task 6: Subscriptions store

**Files:**
- Create: `includes/payments/class-remjobs-subscriptions.php`
- Test: `tests/wpcli/test-subscriptions.php`

**Interfaces:**
- Produces (all static):
  - `Remjobs_Subscriptions::upsert(string $gateway, string $gateway_subscription_id, array $data): int` — inserts or updates by `(gateway, gateway_subscription_id)`; returns row id. `$data` keys: `user_id, scope, job_id, gateway_customer_id, status, current_period_end, cancel_at_period_end`.
  - `Remjobs_Subscriptions::get(int $id): ?array`
  - `Remjobs_Subscriptions::get_by_gateway_id(string $gateway, string $gateway_subscription_id): ?array`
  - `Remjobs_Subscriptions::set_status(int $id, string $status): bool`
  - `Remjobs_Subscriptions::active_for_job(int $job_id): ?array` — an `active` per-job sub for a job
  - `Remjobs_Subscriptions::active_count_for_user(int $user_id): int` — count of `active` account-scope subs’ covered jobs is computed in Phase 2; here just count active subs for the user
  - `Remjobs_Subscriptions::table(): string`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-subscriptions.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-subscriptions.php';
Remjobs_Activator::create_tables();

$id = Remjobs_Subscriptions::upsert('stripe', 'sub_abc', array(
    'user_id' => 7, 'scope' => 'job', 'job_id' => 123,
    'gateway_customer_id' => 'cus_1', 'status' => 'active',
    'current_period_end' => gmdate('Y-m-d H:i:s', time() + 2592000),
));
remjobs_t_assert($id > 0, 'upsert inserts');

// Second upsert updates the same row (no duplicate).
$id2 = Remjobs_Subscriptions::upsert('stripe', 'sub_abc', array('status' => 'past_due'));
remjobs_t_assert($id2 === $id, 'upsert updates same row');
remjobs_t_assert(Remjobs_Subscriptions::get($id)['status'] === 'past_due', 'status updated');

remjobs_t_assert(Remjobs_Subscriptions::get_by_gateway_id('stripe', 'sub_abc')['id'] == $id, 'lookup by gateway id');

Remjobs_Subscriptions::set_status($id, 'active');
remjobs_t_assert(Remjobs_Subscriptions::active_for_job(123)['id'] == $id, 'active_for_job finds it');

global $wpdb;
$wpdb->delete(Remjobs_Subscriptions::table(), array('id' => $id));
remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-subscriptions.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement the subscriptions store**

```php
<?php
/**
 * Subscriptions table store.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Subscriptions
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'remjobs_subscriptions';
    }

    public static function upsert(string $gateway, string $gateway_subscription_id, array $data): int
    {
        global $wpdb;
        $existing = self::get_by_gateway_id($gateway, $gateway_subscription_id);
        $now = gmdate('Y-m-d H:i:s');

        $fields = array();
        foreach (array('user_id', 'scope', 'job_id', 'gateway_customer_id', 'status', 'current_period_end', 'cancel_at_period_end') as $k) {
            if (array_key_exists($k, $data)) {
                $fields[$k] = $data[$k];
            }
        }

        if ($existing) {
            $fields['updated_at'] = $now;
            if (!empty($fields)) {
                $wpdb->update(self::table(), $fields, array('id' => (int) $existing['id']));
            }
            return (int) $existing['id'];
        }

        $row = array_merge(array(
            'user_id'                 => (int) ($data['user_id'] ?? 0),
            'scope'                   => sanitize_text_field((string) ($data['scope'] ?? 'job')),
            'job_id'                  => isset($data['job_id']) ? (int) $data['job_id'] : null,
            'gateway'                 => $gateway,
            'gateway_subscription_id' => $gateway_subscription_id,
            'gateway_customer_id'     => isset($data['gateway_customer_id']) ? sanitize_text_field((string) $data['gateway_customer_id']) : null,
            'status'                  => sanitize_text_field((string) ($data['status'] ?? 'active')),
            'current_period_end'      => $data['current_period_end'] ?? null,
            'cancel_at_period_end'    => (int) ($data['cancel_at_period_end'] ?? 0),
            'created_at'              => $now,
            'updated_at'              => $now,
        ));
        $wpdb->insert(self::table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_gateway_id(string $gateway, string $gateway_subscription_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_subscription_id = %s', $gateway, $gateway_subscription_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function set_status(int $id, string $status): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            self::table(),
            array('status' => sanitize_text_field($status), 'updated_at' => gmdate('Y-m-d H:i:s')),
            array('id' => $id)
        );
    }

    public static function active_for_job(int $job_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE job_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $job_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function active_count_for_user(int $user_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id = %d AND status = 'active'", $user_id)
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-subscriptions.php`
Expected: `RESULT: PASS`.

- [ ] **Step 5: Commit**

```bash
git add includes/payments/class-remjobs-subscriptions.php tests/wpcli/test-subscriptions.php
git commit -m "feat: add subscriptions store with upsert"
```

---

### Task 7: Orchestrator + bootstrap wiring + schema self-heal

**Files:**
- Create: `includes/payments/class-remjobs-payments.php`
- Modify: `includes/class-remjobs-core.php` (require + instantiate)
- Test: `tests/wpcli/test-bootstrap.php`

**Interfaces:**
- Consumes: all Phase-1 classes; `REMJOBS_DB_VERSION`.
- Produces:
  - `Remjobs_Payments::__construct()` requires the payment class files and stores a `Remjobs_Payment_Settings` instance.
  - `Remjobs_Payments::init(): void` — hooks `plugins_loaded` → `maybe_upgrade_schema`, and registers the settings page.
  - `Remjobs_Payments::maybe_upgrade_schema(): void` — if `get_option('remjobs_db_version') !== REMJOBS_DB_VERSION`, call `Remjobs_Activator::create_tables()`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/wpcli/test-bootstrap.php
require __DIR__ . '/_assert.php';

// Simulate a stale/missing schema version, then let the orchestrator self-heal.
delete_option('remjobs_db_version');
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Plans'), 'plans loaded');
remjobs_t_assert(class_exists('Remjobs_Orders'), 'orders loaded');
remjobs_t_assert(class_exists('Remjobs_Subscriptions'), 'subscriptions loaded');
remjobs_t_assert(class_exists('Remjobs_Payment_Settings'), 'settings loaded');

$p->maybe_upgrade_schema();
remjobs_t_assert(get_option('remjobs_db_version') === REMJOBS_DB_VERSION, 'schema self-healed to current version');

remjobs_t_done();
```

- [ ] **Step 2: Run test to verify it fails**

Run: `studio wp eval-file tests/wpcli/test-bootstrap.php`
Expected: FAIL — `Remjobs_Payments` not found.

- [ ] **Step 3: Implement the orchestrator**

```php
<?php
/**
 * Payments orchestrator: loads the payment subsystem and wires its hooks.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Payments
{
    /** @var Remjobs_Payment_Settings */
    private $settings;

    public function __construct()
    {
        $base = REMJOBS_PLUGIN_PATH . 'includes/payments/';
        require_once $base . 'class-remjobs-payment-settings.php';
        require_once $base . 'class-remjobs-plans.php';
        require_once $base . 'class-remjobs-orders.php';
        require_once $base . 'class-remjobs-subscriptions.php';
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-activator.php';

        $this->settings = new Remjobs_Payment_Settings();
    }

    public function init(): void
    {
        add_action('plugins_loaded', array($this, 'maybe_upgrade_schema'), 20);
        $this->settings->register();
    }

    public function maybe_upgrade_schema(): void
    {
        if (get_option('remjobs_db_version') !== REMJOBS_DB_VERSION) {
            Remjobs_Activator::create_tables();
        }
    }
}
```

- [ ] **Step 4: Wire into the core bootstrap**

In `includes/class-remjobs-core.php`, inside `remjobs_load_dependencies()`, after the existing `require_once`/`new` lines, add:

```php
        require_once REMJOBS_PLUGIN_PATH . 'includes/payments/class-remjobs-payments.php';
        $payments = new Remjobs_Payments();
        $payments->init();
```

- [ ] **Step 5: Run test to verify it passes**

Run: `studio wp eval-file tests/wpcli/test-bootstrap.php`
Expected: `RESULT: PASS`.

- [ ] **Step 6: Full Phase-1 regression + smoke the admin page**

Run each test script and confirm all print `RESULT: PASS`:

```bash
for t in schema settings settings-page plans orders subscriptions bootstrap; do
  echo "== $t =="; studio wp eval-file tests/wpcli/test-$t.php;
done
```

Then confirm the site loads and the Payments submenu renders:
```bash
studio wp eval 'echo class_exists("Remjobs_Payments") ? "OK\n" : "MISSING\n";'
```
Visit **wp-admin → Jobs → Payments** and confirm the form renders and saves (Currency/Mode round-trip).

- [ ] **Step 7: Commit**

```bash
git add includes/payments/class-remjobs-payments.php includes/class-remjobs-core.php tests/wpcli/test-bootstrap.php
git commit -m "feat: wire payments orchestrator into plugin bootstrap with schema self-heal"
```

---

## Self-Review (Phase 1 vs spec §3, §4)

- **§3 module files:** `plans`, `orders`, `subscriptions`, `payment-settings`, `payments` orchestrator all created. ✅ (Gateways, `featured`, webhooks are Phases 2–3 — intentionally out of this plan.)
- **§4.1 orders table / §4.2 subscriptions table:** all columns + indexes present (Task 1). ✅
- **§4.4 settings option + defaults + constant override:** Tasks 2–3. ✅
- **Plans config incl. zero-decimal amount math:** Task 4. ✅
- **Idempotency primitive (`mark_paid`, upsert):** Tasks 5–6 — consumed by Phase 3 webhooks. ✅
- **Schema self-heal on version bump:** Task 7. ✅
- **Deferred to later phases (not gaps):** `_remjobs_featured*` meta + normalization (Phase 2/4), cron (Phase 2), gateway REST + webhook routes (Phase 3), submit/My-Jobs/orders-list UI (Phase 4).
- **Placeholder scan:** none. The only cross-task note is Task 1→2 ordering for `defaults()`, explicitly called out with a resolution.
- **Type consistency:** store method names/signatures in the Interfaces blocks match their call sites in later-phase descriptions (`mark_paid`, `upsert`, `active_for_job`, `amount_minor`).
