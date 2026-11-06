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
