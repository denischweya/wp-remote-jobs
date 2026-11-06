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
