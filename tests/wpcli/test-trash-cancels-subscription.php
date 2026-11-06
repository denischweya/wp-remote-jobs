<?php
// tests/wpcli/test-trash-cancels-subscription.php — FIX I2: trashing/deleting a
// featured job with an active per-job subscription cancels it at the gateway.
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/';
require_once $base . 'class-remjobs-activator.php';
require_once $base . 'payments/class-remjobs-payment-settings.php';
require_once $base . 'payments/class-remjobs-plans.php';
require_once $base . 'payments/class-remjobs-orders.php';
require_once $base . 'payments/class-remjobs-subscriptions.php';
require_once $base . 'payments/class-remjobs-featured.php';
require_once $base . 'payments/interface-remjobs-gateway.php';
require_once $base . 'payments/class-remjobs-checkout.php';
require_once $base . 'payments/class-remjobs-payment-ui.php';
Remjobs_Activator::create_tables();
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

// A gateway stub that records cancel_subscription() calls.
class Remjobs_Gateway_Cancel_Spy implements Remjobs_Payment_Gateway
{
    public $cancel_calls = array();
    public function get_id(): string { return 'stub'; }
    public function is_configured(): bool { return true; }
    public function create_checkout(array $o, array $p, string $r, string $c): string { return ''; }
    public function parse_webhook(WP_REST_Request $request): ?array { return null; }
    public function cancel_subscription(string $gateway_subscription_id): bool
    {
        $this->cancel_calls[] = $gateway_subscription_id;
        return true;
    }
}

$featured = new Remjobs_Featured();
$gateway_spy = new Remjobs_Gateway_Cancel_Spy();
$checkout = new Remjobs_Checkout(array('stub' => $gateway_spy), $featured);
$ui = new Remjobs_Payment_Ui($checkout, array('stub' => $gateway_spy), $featured);

// --- Featured job + active per-job subscription ---
$job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'trash-cancel job', 'post_author' => 1));
update_post_meta($job, '_remjobs_featured', '1');
update_post_meta($job, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
$sub_id = Remjobs_Subscriptions::upsert('stub', 'sub_trash_1', array(
    'user_id' => 1, 'scope' => 'job', 'job_id' => $job,
    'gateway_customer_id' => 'cus_1', 'status' => 'active',
    'current_period_end' => gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS),
));
update_post_meta($job, '_remjobs_feature_subscription_id', $sub_id);

remjobs_t_assert(Remjobs_Subscriptions::active_for_job($job)['id'] == $sub_id, 'precondition: active per-job subscription exists');

// --- Trigger the trash handler directly (same signature as the wp_trash_post hook) ---
$ui->cancel_subscription_for_deleted_job($job);

remjobs_t_assert(count($gateway_spy->cancel_calls) === 1, 'gateway cancel_subscription called once on trash');
remjobs_t_assert($gateway_spy->cancel_calls[0] === 'sub_trash_1', 'gateway cancel_subscription called with the correct gateway_subscription_id');
$sub_after = Remjobs_Subscriptions::get($sub_id);
remjobs_t_assert($sub_after['status'] === 'canceled', 'subscription marked canceled after trash');

// --- Idempotent: calling again (e.g. before_delete_post firing after wp_trash_post) is a no-op ---
$ui->cancel_subscription_for_deleted_job($job);
remjobs_t_assert(count($gateway_spy->cancel_calls) === 1, 'second call does not re-cancel an already-canceled subscription');

// --- Non-remjobs_jobs post type is ignored ---
$other_post = wp_insert_post(array('post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'unrelated post'));
$ui->cancel_subscription_for_deleted_job($other_post);
remjobs_t_assert(count($gateway_spy->cancel_calls) === 1, 'non-remjobs_jobs post type does not trigger a cancel call');

// --- A job with no active subscription is a no-op (no crash) ---
$job_no_sub = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'no-sub job', 'post_author' => 1));
$ui->cancel_subscription_for_deleted_job($job_no_sub);
remjobs_t_assert(count($gateway_spy->cancel_calls) === 1, 'job with no active subscription triggers no cancel call');

// --- Hooks are registered via register() ---
$ui->register();
remjobs_t_assert(has_action('wp_trash_post', array($ui, 'cancel_subscription_for_deleted_job')) !== false, 'wp_trash_post hooked to the cancel handler');
remjobs_t_assert(has_action('before_delete_post', array($ui, 'cancel_subscription_for_deleted_job')) !== false, 'before_delete_post hooked to the cancel handler');

wp_delete_post($job, true);
wp_delete_post($other_post, true);
wp_delete_post($job_no_sub, true);
global $wpdb;
$wpdb->delete(Remjobs_Subscriptions::table(), array('id' => $sub_id));
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
