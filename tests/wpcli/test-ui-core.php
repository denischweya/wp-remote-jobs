<?php
// tests/wpcli/test-ui-core.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Payment_Ui'), 'ui class loaded');

// enabled_gateway_choices respects settings
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'enabled_gateways' => array('stripe'),
    'stripe_secret_key' => 'sk',
    'stripe_publishable_key' => 'pk',
    'paypal_client_id' => '',
    'paypal_secret' => '',
)));

$p->init();
remjobs_t_assert(shortcode_exists('remjobs_my_jobs'), 'my jobs shortcode registered');
remjobs_t_assert(has_filter('remjobs_account_url'), 'account_url filter registered');
remjobs_t_assert(has_action('admin_post_remjobs_start_checkout'), 'start checkout admin-post hooked');
remjobs_t_assert(has_action('admin_post_remjobs_cancel_subscription'), 'cancel subscription admin-post hooked');

// accessor consistency: orchestrator exposes ui() mirroring checkout()
remjobs_t_assert(method_exists($p, 'ui'), 'orchestrator exposes ui() accessor');
remjobs_t_assert($p->ui() instanceof Remjobs_Payment_Ui, 'ui() returns a Remjobs_Payment_Ui instance');

// enabled_gateway_choices via the ui() accessor: only configured+enabled gateways appear
$choices = $p->ui()->enabled_gateway_choices();
remjobs_t_assert(isset($choices['stripe']), 'stripe is in enabled_gateway_choices (enabled + configured)');
remjobs_t_assert(!isset($choices['paypal']), 'paypal excluded from enabled_gateway_choices (not configured)');

// remjobs_payments() global accessor
remjobs_t_assert(function_exists('remjobs_payments'), 'remjobs_payments() function exists');
remjobs_t_assert(remjobs_payments() === $p, 'remjobs_payments() returns the booted orchestrator instance');

// account_url points at My Jobs page when set
$pg = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'My Jobs'));
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('my_jobs_page_id' => $pg)));
remjobs_t_assert(apply_filters('remjobs_account_url', home_url('/')) === get_permalink($pg), 'account_url -> my jobs page');

wp_delete_post($pg, true);
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

remjobs_t_done();
