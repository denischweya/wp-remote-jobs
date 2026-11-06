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
remjobs_t_assert(Remjobs_Subscriptions::active_count_for_user(7) >= 1, 'active_count_for_user');

global $wpdb;
$wpdb->delete(Remjobs_Subscriptions::table(), array('id' => $id));
remjobs_t_done();
