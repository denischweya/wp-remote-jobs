<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/';
require_once $base . 'class-remjobs-activator.php';
require_once $base . 'payments/class-remjobs-payment-settings.php';
require_once $base . 'payments/class-remjobs-plans.php';
require_once $base . 'payments/class-remjobs-orders.php';
require_once $base . 'payments/class-remjobs-subscriptions.php';
require_once $base . 'payments/class-remjobs-featured.php';
require_once $base . 'payments/class-remjobs-fulfillment.php';
Remjobs_Activator::create_tables();
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

$featured = new Remjobs_Featured();
$fulfillment = new Remjobs_Fulfillment($featured);

// --- One-time (single) order fulfillment ---
$job1 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'job1', 'post_author' => 1));
$order1_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job1, 'plan' => 'single', 'gateway' => 'stripe', 'amount' => '49.00', 'currency' => 'USD'));

$fulfillment->fulfill($order1_id, array('event_id' => 'e1', 'gateway_order_id' => 'cs_1'));

$order1 = Remjobs_Orders::get($order1_id);
remjobs_t_assert($order1['status'] === 'paid', 'order marked paid');
remjobs_t_assert(get_post_status($job1) === 'publish', 'job published');
remjobs_t_assert($featured->is_featured($job1) === true, 'job featured');

// --- Idempotency guard: calling fulfill again on an already-paid order is a no-op ---
// Use a second job/order to independently verify the guard path (order already paid -> early return).
$job2 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'job2', 'post_author' => 1));
$order2_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job2, 'plan' => 'single', 'gateway' => 'stripe', 'amount' => '49.00', 'currency' => 'USD'));
Remjobs_Orders::mark_paid($order2_id, 'cs_2', 'e2');
// job2 intentionally left un-featured/un-published so we can detect whether fulfill() re-processes it.
$fulfillment->fulfill($order2_id, array('event_id' => 'e2', 'gateway_order_id' => 'cs_2'));
remjobs_t_assert(get_post_status($job2) === 'draft', 'already-paid order is not re-processed (idempotency guard short-circuits)');
remjobs_t_assert($featured->is_featured($job2) === false, 'already-paid order does not retroactively feature the job');

// Re-run fulfill on order1 (first order) to double-check no double side effects.
$fulfillment->fulfill($order1_id, array('event_id' => 'e1', 'gateway_order_id' => 'cs_1'));
$order1_again = Remjobs_Orders::get($order1_id);
remjobs_t_assert($order1_again['status'] === 'paid', 'order1 still paid after repeat fulfill call');
remjobs_t_assert($featured->is_featured($job1) === true, 'order1 job still featured after repeat fulfill call');

// --- Subscription (monthly) order fulfillment ---
$job3 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'draft', 'post_title' => 'job3', 'post_author' => 1));
$order3_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job3, 'plan' => 'monthly', 'gateway' => 'stripe', 'amount' => '99.00', 'currency' => 'USD'));
$period_end = gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS);
$fulfillment->fulfill($order3_id, array(
    'event_id' => 'e3',
    'gateway_order_id' => 'cs_3',
    'subscription' => array(
        'gateway_subscription_id' => 'sub_3',
        'gateway_customer_id' => 'cus_3',
        'current_period_end' => $period_end,
        'status' => 'active',
    ),
));

$order3 = Remjobs_Orders::get($order3_id);
remjobs_t_assert($order3['status'] === 'paid', 'subscription order marked paid');
remjobs_t_assert(!empty($order3['subscription_id']), 'order has subscription_id');
$sub3 = Remjobs_Subscriptions::get((int) $order3['subscription_id']);
remjobs_t_assert($sub3 !== null && $sub3['gateway_subscription_id'] === 'sub_3', 'subscriptions row exists');
remjobs_t_assert($featured->is_featured($job3) === true, 'subscription job featured');
remjobs_t_assert(get_post_meta($job3, '_remjobs_featured_expires', true) === $period_end, 'job expiry set to period end');

// --- get_by_gateway_id_any helper ---
remjobs_t_assert(Remjobs_Subscriptions::get_by_gateway_id_any('sub_3')['id'] == $sub3['id'], 'get_by_gateway_id_any finds subscription without gateway label');
remjobs_t_assert(Remjobs_Subscriptions::get_by_gateway_id_any('sub_nonexistent') === null, 'get_by_gateway_id_any returns null for unknown id');

foreach (array($job1, $job2, $job3) as $id) {
    wp_delete_post($id, true);
}
global $wpdb;
$wpdb->query("DELETE FROM " . Remjobs_Orders::table() . " WHERE id IN ({$order1_id}, {$order2_id}, {$order3_id})");
$wpdb->query("DELETE FROM " . Remjobs_Subscriptions::table() . " WHERE gateway_subscription_id = 'sub_3'");
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
