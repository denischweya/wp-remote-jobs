# Paid Featured Listings — Phase 2: Featured Lifecycle + Cron — Implementation Plan

**Goal:** Implement the featured-listing lifecycle (activate on payment, expire on schedule), the hourly expiry cron, abandoned-draft cleanup, and normalize the pre-existing `_remjobs_featured` / `_remjobs_is_premium` inconsistency — all testable without any gateway.

**Architecture:** A single `Remjobs_Featured` engine centralizes every job-state change. It is instantiated and wired by the `Remjobs_Payments` orchestrator (built in Phase 1). Cron is scheduled on activation and self-heals on `init`. No HTTP/gateway code here — `activate_feature()` is called directly by tests now and by webhooks in Phase 3.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, WP-Cron, `$wpdb`, WP-CLI (`studio wp`).

**Spec:** `docs/superpowers/specs/2026-11-06-remote-jobs-payments-design.md` (§4.3 meta, §6 lifecycle & expiry).

## Global Constraints (identical to Phase 1 — bind every task)

- PHP floor **7.4**; WordPress floor **5.8**. No PHP 8-only syntax.
- Text domain **`remote-jobs`**; class prefix **`Remjobs_`**; one class per file; `if (!defined('ABSPATH')) exit;` guard; `declare(strict_types=1);` in new class files.
- All stored timestamps **UTC** via `gmdate('Y-m-d H:i:s')`; all expiry comparisons in UTC (use `time()` / `gmdate`).
- All table access via `$wpdb->prepare`; sanitize input, escape output.
- Run every wp command as **`studio wp`**; tests live in `tests/wpcli/`, run from the site root `/Users/denis/Studio/wp-remote-jobs` as `studio wp eval-file wp-content/plugins/remote-jobs/tests/wpcli/test-NAME.php`, and must print `RESULT: PASS`. Filter noise with `grep -viE "Deprecated:|react/promise"`.
- **Match the Phase 1 code style** — read `includes/payments/class-remjobs-orders.php` and `class-remjobs-payments.php` first and follow their structure, docblocks, and `require_once` conventions.
- Reuse the Phase 1 test harness: every test starts `require __DIR__ . '/_assert.php';` and ends `remjobs_t_done();`.
- Commit per task on branch `feature/paid-featured-listings`, `git add` only the explicit files for that task, each message ending with the `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>` trailer. Never `git add -A` (the repo contains unrelated files and a scratch ledger).
- `php -l` is unusable here (broken Homebrew PHP). Validate syntax only via `studio wp`.

## Meta keys (spec §4.3) — canonical definitions

| Meta | Values | Set by |
|---|---|---|
| `_remjobs_featured` | `'1'` / `''` | canonical featured flag |
| `_remjobs_is_premium` | `'1'` / `''` | kept in sync with `_remjobs_featured` (back-compat) |
| `_remjobs_featured_expires` | `Y-m-d H:i:s` UTC | when the current feature lapses |
| `_remjobs_feature_plan` | `single` / `monthly` | |
| `_remjobs_feature_order_id` | int | |
| `_remjobs_feature_subscription_id` | int | monthly only |
| `_remjobs_payment_status` | `awaiting` / `paid` / `''` | |
| `_remjobs_expired` | `'1'` | set only when `unpublish`-behavior expiry drafts a job |

## File Structure

| File | Responsibility |
|---|---|
| `includes/payments/class-remjobs-featured.php` *(create)* | `Remjobs_Featured` lifecycle engine + cron callback |
| `includes/payments/class-remjobs-payments.php` *(modify)* | require + instantiate `Remjobs_Featured`; register cron hook; schedule/self-heal |
| `includes/class-remjobs-activator.php` *(modify)* | schedule the cron event on activation |
| `includes/class-remjobs-deactivator.php` *(modify)* | clear the cron event on deactivation |
| `includes/blocks/job-list/job-list.php` *(modify)* | change the `featuredOnly` meta_query key `_remjobs_is_premium` → `_remjobs_featured` |
| `tests/wpcli/test-featured.php` *(create)* | activate/expire behavior |
| `tests/wpcli/test-expiry-sweep.php` *(create)* | cron sweep + abandoned-draft cleanup + subscription protection |
| `tests/wpcli/test-cron.php` *(create)* | cron event scheduled |

---

### Task 1: `Remjobs_Featured` — activate / expire / extend

**Files:** Create `includes/payments/class-remjobs-featured.php`; Test `tests/wpcli/test-featured.php`.

**Interfaces (produce — exact signatures; Phase 3 depends on these):**
- `const CRON_HOOK = 'remjobs_feature_expiry_check';`
- `public function activate_feature(int $job_id, string $plan, array $context = array()): void`
  - `$context` keys (all optional): `order_id` (int), `subscription_id` (int), `current_period_end` (string `Y-m-d H:i:s` UTC, for monthly).
  - Behavior: if the post status is not `publish`, set it to `publish` (via `wp_update_post`). Set meta: `_remjobs_featured='1'`, `_remjobs_is_premium='1'`, `_remjobs_feature_plan=$plan`, `_remjobs_payment_status='paid'`; delete `_remjobs_expired`. If `order_id` given set `_remjobs_feature_order_id`; if `subscription_id` given set `_remjobs_feature_subscription_id`. Compute expiry: for `single`, `gmdate('Y-m-d H:i:s', time() + duration_days*DAY_IN_SECONDS)` using `Remjobs_Plans::get('single')['duration_days']`; for `monthly`, use `$context['current_period_end']` if present, else `now + 30 days`. Store in `_remjobs_featured_expires`.
- `public function extend_feature(int $job_id, string $new_expires_utc): void` — update `_remjobs_featured_expires` (renewal).
- `public function expire_feature(int $job_id): void` — read `Remjobs_Payment_Settings::get('expiry_behavior')`. If `unpublish`: `wp_update_post(['ID'=>$job_id,'post_status'=>'draft'])`, set `_remjobs_expired='1'`. Always (both behaviors): set `_remjobs_featured=''`, `_remjobs_is_premium=''`, delete `_remjobs_featured_expires`.
- `public function is_featured(int $job_id): bool` — `get_post_meta($job_id,'_remjobs_featured',true)==='1'`.
- `public function count_featured_for_user(int $user_id): int` — count `publish` `remjobs_jobs` authored by `$user_id` with `_remjobs_featured='1'` (use `get_posts` with meta_query + `author` + `fields=>ids`, `posts_per_page=>-1`).
- `public function can_feature(int $user_id): bool` — for account-scope monthly: `cap = (int) Remjobs_Payment_Settings::get('monthly_account_cap'); return cap === 0 || count_featured_for_user($user_id) < cap;`. (Single/per-job always allowed → callers pass through; this method is only consulted for account scope.)

**Test (`test-featured.php`) — create a real job, exercise both behaviors:**
```php
<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'class-remjobs-plans.php';
require_once $base . 'class-remjobs-featured.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('single_duration_days' => 30, 'expiry_behavior' => 'unfeature')));

$f = new Remjobs_Featured();
$job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'LC test', 'post_author' => 1));

// activate single
$f->activate_feature($job, 'single', array('order_id' => 99));
remjobs_t_assert(get_post_status($job) === 'publish', 'activate publishes draft');
remjobs_t_assert($f->is_featured($job) === true, 'is featured');
remjobs_t_assert(get_post_meta($job, '_remjobs_is_premium', true) === '1', 'premium synced');
remjobs_t_assert(get_post_meta($job, '_remjobs_feature_order_id', true) == 99, 'order id stored');
$exp = get_post_meta($job, '_remjobs_featured_expires', true);
$days = (int) round((strtotime($exp) - time()) / DAY_IN_SECONDS);
remjobs_t_assert($days === 30, 'single expiry ~30 days out');

// expire with unfeature
$f->expire_feature($job);
remjobs_t_assert(get_post_status($job) === 'publish', 'unfeature keeps published');
remjobs_t_assert($f->is_featured($job) === false, 'unfeature clears flag');

// expire with unpublish
$f->activate_feature($job, 'single');
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('expiry_behavior' => 'unpublish')));
$f->expire_feature($job);
remjobs_t_assert(get_post_status($job) === 'draft', 'unpublish drafts job');
remjobs_t_assert(get_post_meta($job, '_remjobs_expired', true) === '1', 'expired flag set');

// monthly uses current_period_end
$cpe = gmdate('Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS);
$f->activate_feature($job, 'monthly', array('subscription_id' => 5, 'current_period_end' => $cpe));
remjobs_t_assert(get_post_meta($job, '_remjobs_featured_expires', true) === $cpe, 'monthly expiry = period end');
remjobs_t_assert(get_post_meta($job, '_remjobs_feature_plan', true) === 'monthly', 'plan monthly');

// cap enforcement (account scope, cap 1 — one featured already exists above)
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('monthly_scope' => 'account', 'monthly_account_cap' => 1)));
remjobs_t_assert($f->can_feature(1) === false, 'cap reached blocks new feature');
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('monthly_account_cap' => 0)));
remjobs_t_assert($f->can_feature(1) === true, 'cap 0 = unlimited');

wp_delete_post($job, true);
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
```

**Steps:** write the test → run (red, class missing) → implement the class → run (green) → commit `feat: add featured lifecycle engine (activate/expire/extend)`.

---

### Task 2: Expiry sweep + abandoned-draft cleanup

**Files:** Modify `includes/payments/class-remjobs-featured.php`; Test `tests/wpcli/test-expiry-sweep.php`.

**Interfaces (produce):**
- `public function run_expiry_sweep(): void` — the `CRON_HOOK` callback. Two passes:
  1. **Expire lapsed features:** `get_posts(['post_type'=>'remjobs_jobs','post_status'=>'publish','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_remjobs_featured','value'=>'1'],['key'=>'_remjobs_featured_expires','value'=>gmdate('Y-m-d H:i:s'),'compare'=>'<','type'=>'DATETIME']]])`. For each, **skip if protected by an active subscription**: `Remjobs_Subscriptions::active_for_job($id)` is non-null → continue (do not expire). Otherwise `expire_feature($id)`.
  2. **Delete abandoned awaiting-payment drafts:** `get_posts(['post_type'=>'remjobs_jobs','post_status'=>'draft','posts_per_page'=>-1,'fields'=>'ids','meta_query'=>[['key'=>'_remjobs_payment_status','value'=>'awaiting']]])`; for each, if `get_post_time('U', true, $id)` is older than 48h (`time() - 48*HOUR_IN_SECONDS`), `wp_delete_post($id, true)`.
- Require `class-remjobs-subscriptions.php` where needed (the orchestrator already loads it; a defensive `require_once` is fine).

**Test (`test-expiry-sweep.php`):**
```php
<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/';
require_once $base . 'class-remjobs-activator.php';
require_once $base . 'payments/class-remjobs-payment-settings.php';
require_once $base . 'payments/class-remjobs-plans.php';
require_once $base . 'payments/class-remjobs-subscriptions.php';
require_once $base . 'payments/class-remjobs-featured.php';
Remjobs_Activator::create_tables();
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('expiry_behavior' => 'unfeature')));
$f = new Remjobs_Featured();

// A lapsed featured job -> should expire
$lapsed = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'lapsed','post_author'=>1));
update_post_meta($lapsed, '_remjobs_featured', '1');
update_post_meta($lapsed, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() - 3600));

// A lapsed featured job PROTECTED by an active subscription -> must NOT expire
$prot = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'protected','post_author'=>1));
update_post_meta($prot, '_remjobs_featured', '1');
update_post_meta($prot, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() - 3600));
Remjobs_Subscriptions::upsert('stripe', 'sub_prot_'.$prot, array('user_id'=>1,'scope'=>'job','job_id'=>$prot,'status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s', time()+86400)));

// A future-dated featured job -> untouched
$future = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'future','post_author'=>1));
update_post_meta($future, '_remjobs_featured', '1');
update_post_meta($future, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 86400));

$f->run_expiry_sweep();
remjobs_t_assert($f->is_featured($lapsed) === false, 'lapsed feature expired');
remjobs_t_assert($f->is_featured($prot) === true, 'subscription-protected feature kept');
remjobs_t_assert($f->is_featured($future) === true, 'future feature untouched');

foreach (array($lapsed, $prot, $future) as $id) { wp_delete_post($id, true); }
global $wpdb; $wpdb->query("DELETE FROM " . $wpdb->prefix . "remjobs_subscriptions WHERE gateway_subscription_id LIKE 'sub_prot_%'");
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
```
> Note on abandoned-draft cleanup: `get_post_time` is hard to backdate in a test (post_date is set on insert). Assert the sweep runs without error and leaves a fresh awaiting-draft in place (not older than 48h), rather than forcing a 48h-old post. Add: create a `draft` with `_remjobs_payment_status='awaiting'`, run sweep, assert it still exists (fresh drafts are preserved), then delete it.

**Steps:** write test → run (red) → implement `run_expiry_sweep()` → run (green) → commit `feat: add featured expiry sweep with subscription protection and draft cleanup`.

---

### Task 3: Cron scheduling + orchestrator wiring + activator/deactivator

**Files:** Modify `class-remjobs-payments.php`, `class-remjobs-activator.php`, `class-remjobs-deactivator.php`; Test `tests/wpcli/test-cron.php`.

**Behavior:**
- Orchestrator `__construct`: also `require_once` `class-remjobs-featured.php`, keep a `Remjobs_Featured` instance.
- Orchestrator `init()`: add these —
  - `add_action(Remjobs_Featured::CRON_HOOK, array($this->featured, 'run_expiry_sweep'));`
  - `add_action('init', array($this, 'maybe_schedule_cron'));`
- New `public function maybe_schedule_cron(): void`: `if (!wp_next_scheduled(Remjobs_Featured::CRON_HOOK)) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', Remjobs_Featured::CRON_HOOK); }`.
- Activator `activate()`: after `create_tables()`, add `if (!wp_next_scheduled('remjobs_feature_expiry_check')) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'remjobs_feature_expiry_check'); }`.
- Deactivator `deactivate()`: add `wp_clear_scheduled_hook('remjobs_feature_expiry_check');` (read the current deactivator first; preserve existing behavior).

**Test (`test-cron.php`):**
```php
<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
$p->maybe_schedule_cron();
remjobs_t_assert(wp_next_scheduled(Remjobs_Featured::CRON_HOOK) !== false, 'expiry cron scheduled');
remjobs_t_assert(class_exists('Remjobs_Featured'), 'featured class loaded by orchestrator');
remjobs_t_done();
```

**Steps:** write test → run (red) → implement wiring → run (green) → commit `feat: schedule hourly featured-expiry cron and wire lifecycle engine`.

---

### Task 4: Normalize featured meta (fix `_remjobs_is_premium` vs `_remjobs_featured`)

**Files:** Modify `includes/blocks/job-list/job-list.php`; add a one-time migration in the orchestrator; Test `tests/wpcli/test-featured-normalize.php`.

**Behavior:**
- In `job-list.php`, the `featuredOnly` meta_query currently keys on `_remjobs_is_premium`. Change that key to `_remjobs_featured` (read the file around the Phase-1-era line ~137-141; there may be two query builders — update each `featuredOnly` branch). The badge already reads `_remjobs_featured` (`$featured = get_post_meta(..., '_remjobs_featured', true)`), so after this both filter and badge agree.
- One-time migration in the orchestrator: `public function maybe_migrate_featured(): void` guarded by an option `remjobs_featured_migrated`. When unset: for every `remjobs_jobs` with `_remjobs_is_premium='1'`, ensure `_remjobs_featured='1'`; then `update_option('remjobs_featured_migrated', '1')`. Call it from `init()` (after schema). Use a bounded `get_posts(... 'fields'=>'ids', 'posts_per_page'=>-1, meta_query _remjobs_is_premium=1)`.

**Test (`test-featured-normalize.php`):**
```php
<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
delete_option('remjobs_featured_migrated');
$legacy = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'legacy premium','post_author'=>1));
update_post_meta($legacy, '_remjobs_is_premium', '1'); // old flag only
$p = new Remjobs_Payments();
$p->maybe_migrate_featured();
remjobs_t_assert(get_post_meta($legacy, '_remjobs_featured', true) === '1', 'legacy premium backfilled to featured');
remjobs_t_assert(get_option('remjobs_featured_migrated') === '1', 'migration flag set');
wp_delete_post($legacy, true);
remjobs_t_done();
```

**Steps:** write test → run (red) → implement migration + job-list.php key change → run (green) → commit `fix: normalize featured meta and backfill legacy premium flag`.

---

### Task 5: Phase 2 regression + live smoke

**Not a code task — a verification gate. Steps:**
- Run all Phase 1 + Phase 2 tests; every one must print `RESULT: PASS`:
  `for t in schema settings settings-page plans orders subscriptions bootstrap featured expiry-sweep cron featured-normalize; do echo "== $t =="; studio wp eval-file wp-content/plugins/remote-jobs/tests/wpcli/test-$t.php 2>&1 | grep -viE "Deprecated:|react/promise" | grep RESULT; done`
- Live smoke: `curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8881/` and `.../jobs/` → both `200`; homepage has no "critical error".
- Confirm cron registered in a normal request: `studio wp cron event list --format=csv | grep remjobs_feature_expiry_check`.
- If all green, write the Phase 2 report and stop. Do **not** modify Phase 1 files beyond the two listed (`job-list.php`, activator, deactivator).

## Self-Review (plan vs spec §6)

- activate/expire/extend, single vs monthly expiry, both expiry behaviors → Task 1. ✅
- cron sweep + subscription protection + abandoned-draft cleanup → Task 2. ✅
- hourly schedule + self-heal + activation/deactivation → Task 3. ✅
- account-cap enforcement (`can_feature`) → Task 1. ✅
- featured-meta normalization + legacy backfill → Task 4. ✅
- Deferred to Phase 3/4 (not gaps): who *calls* `activate_feature` (webhooks = Phase 3), UI (Phase 4).
