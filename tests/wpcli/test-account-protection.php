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

// Account-scope active subscription for user 1 (no job_id).
$sub_id = Remjobs_Subscriptions::upsert('stripe', 'sub_acct_test', array(
    'user_id' => 1, 'scope' => 'account', 'job_id' => null,
    'gateway_customer_id' => 'cus_acct', 'status' => 'active',
    'current_period_end' => gmdate('Y-m-d H:i:s', time() + 86400),
));

// Lapsed featured job authored by user 1, with no per-job subscription.
$job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'acct protected', 'post_author' => 1));
update_post_meta($job, '_remjobs_featured', '1');
update_post_meta($job, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() - 3600));

remjobs_t_assert(Remjobs_Subscriptions::active_account_for_user(1)['id'] == $sub_id, 'active_account_for_user finds the account subscription');
remjobs_t_assert(Remjobs_Subscriptions::active_account_for_user(999) === null, 'active_account_for_user returns null for other users');

$f->run_expiry_sweep();
remjobs_t_assert($f->is_featured($job) === true, 'account-subscription protects lapsed job from sweep');

// Cancel the account subscription; sweep should now expire the job.
Remjobs_Subscriptions::set_status($sub_id, 'canceled');
$f->run_expiry_sweep();
remjobs_t_assert($f->is_featured($job) === false, 'job expires once account subscription is canceled');

wp_delete_post($job, true);
global $wpdb;
$wpdb->delete(Remjobs_Subscriptions::table(), array('id' => $sub_id));
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
