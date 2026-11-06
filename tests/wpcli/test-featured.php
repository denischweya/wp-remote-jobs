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
