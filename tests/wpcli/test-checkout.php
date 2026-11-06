<?php
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
Remjobs_Activator::create_tables();
// Enable the 'stub' gateway so the enabled_gateways enforcement (I2) permits
// the happy-path assertions below.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('enabled_gateways' => array('stub'))));

$featured = new Remjobs_Featured();

// Stub gateway: records the order passed to create_checkout and returns a known URL.
$stub = new class implements Remjobs_Payment_Gateway {
    public $captured_order = null;
    public function get_id(): string { return 'stub'; }
    public function is_configured(): bool { return true; }
    public function create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string
    {
        $this->captured_order = $order;
        return 'https://gateway.example/checkout/abc';
    }
    public function parse_webhook(WP_REST_Request $request): ?array { return null; }
    public function cancel_subscription(string $gateway_subscription_id): bool { return true; }
};

$checkout = new Remjobs_Checkout(array('stub' => $stub), $featured);

$user1 = 1;
$user2 = 2;
$job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'checkout job', 'post_author' => $user1));
$other_job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'other user job', 'post_author' => $user2));

$url = $checkout->start($user1, $job, 'single', 'stub');
remjobs_t_assert($url === 'https://gateway.example/checkout/abc', 'start returns gateway redirect url');
remjobs_t_assert((int) $stub->captured_order['user_id'] === $user1, 'gateway receives order with correct user_id');
remjobs_t_assert((int) $stub->captured_order['job_id'] === $job, 'gateway receives order with correct job_id');
remjobs_t_assert($stub->captured_order['plan'] === 'single', 'gateway receives order with correct plan');

$order_row = Remjobs_Orders::get_by_key($stub->captured_order['order_key']);
remjobs_t_assert($order_row !== null, 'pending order row was created');
remjobs_t_assert($order_row['status'] === 'pending', 'created order is pending');
remjobs_t_assert($order_row['plan'] === 'single', 'order plan matches');
remjobs_t_assert((float) $order_row['amount'] === (float) Remjobs_Plans::get('single')['price'], 'order amount matches server-side plan price');
remjobs_t_assert($order_row['currency'] === Remjobs_Plans::get('single')['currency'], 'order currency matches server-side plan currency');

// Ownership guard: user1 cannot pay for user2's job.
$threw = false;
try {
    $checkout->start($user1, $other_job, 'single', 'stub');
} catch (Exception $e) {
    $threw = true;
}
remjobs_t_assert($threw === true, 'ownership guard rejects paying for another user\'s job');

// C1 (cap): scope=account, cap already reached -> rejected at the featuring boundary.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('enabled_gateways' => array('stub'), 'monthly_scope' => 'account', 'monthly_account_cap' => 1)));
$capped_job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'already featured', 'post_author' => $user1));
update_post_meta($capped_job, '_remjobs_featured', '1');

remjobs_t_assert($featured->can_feature($user1) === false, 'can_feature returns false at cap');

$threw_cap = false;
try {
    $checkout->start($user1, $job, 'monthly', 'stub');
} catch (Exception $e) {
    $threw_cap = true;
}
remjobs_t_assert($threw_cap === true, 'account-cap guard rejects when cap already reached');

// C1 (one account subscription per user): with an active account subscription,
// a second account-scope checkout is rejected outright (closes the multi-sub race).
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('enabled_gateways' => array('stub'), 'monthly_scope' => 'account', 'monthly_account_cap' => 0)));
$sub_id = Remjobs_Subscriptions::upsert('stub', 'sub_acct_chk', array('user_id' => $user1, 'scope' => 'account', 'job_id' => null, 'status' => 'active', 'current_period_end' => gmdate('Y-m-d H:i:s', time() + 86400)));
$threw_dup_sub = false;
try {
    $checkout->start($user1, $job, 'monthly', 'stub');
} catch (Exception $e) {
    $threw_dup_sub = true;
}
remjobs_t_assert($threw_dup_sub === true, 'second account-scope checkout blocked while an account subscription is active');
Remjobs_Subscriptions::set_status($sub_id, 'canceled');

// I2: a configured but DISABLED gateway is rejected even though is_configured() is true.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('enabled_gateways' => array('paypal'))));
$threw_disabled = false;
try {
    $checkout->start($user1, $job, 'single', 'stub'); // stub is configured, but not in enabled_gateways
} catch (Exception $e) {
    $threw_disabled = true;
}
remjobs_t_assert($threw_disabled === true, 'disabled (not-enabled) gateway rejected despite being configured');

// Restore stub-enabled for the remaining guard assertions.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('enabled_gateways' => array('stub'))));

// Unknown plan / unconfigured gateway guards.
$threw_plan = false;
try {
    $checkout->start($user1, $job, 'bogus', 'stub');
} catch (Exception $e) {
    $threw_plan = true;
}
remjobs_t_assert($threw_plan === true, 'unknown plan rejected');

$threw_gateway = false;
try {
    $checkout->start($user1, $job, 'single', 'nonexistent');
} catch (Exception $e) {
    $threw_gateway = true;
}
remjobs_t_assert($threw_gateway === true, 'unconfigured/unknown gateway rejected');

foreach (array($job, $other_job, $capped_job) as $id) {
    wp_delete_post($id, true);
}
global $wpdb;
$wpdb->query("DELETE FROM " . Remjobs_Orders::table() . " WHERE user_id IN ({$user1}, {$user2})");
$wpdb->query("DELETE FROM " . Remjobs_Subscriptions::table() . " WHERE gateway_subscription_id = 'sub_acct_chk'");
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
