<?php
// tests/wpcli/test-ui-wiring.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

// my_jobs_page_id present in defaults, defaults to 0
$defaults = Remjobs_Payment_Settings::defaults();
remjobs_t_assert(array_key_exists('my_jobs_page_id', $defaults), 'my_jobs_page_id present in defaults');
remjobs_t_assert((int) $defaults['my_jobs_page_id'] === 0, 'my_jobs_page_id defaults to 0');

// sanitize() coerces to a non-negative int
$sanitized = Remjobs_Payment_Settings::sanitize(array('my_jobs_page_id' => '42abc'));
remjobs_t_assert((int) $sanitized['my_jobs_page_id'] === 42, 'sanitize coerces my_jobs_page_id to int');

$sanitized_neg = Remjobs_Payment_Settings::sanitize(array('my_jobs_page_id' => -5));
remjobs_t_assert((int) $sanitized_neg['my_jobs_page_id'] === 0, 'sanitize floors negative my_jobs_page_id to 0');

$sanitized_missing = Remjobs_Payment_Settings::sanitize(array());
remjobs_t_assert((int) $sanitized_missing['my_jobs_page_id'] === 0, 'sanitize defaults missing my_jobs_page_id to 0');

// Orchestrator registers the UI: shortcode + admin-post actions present.
$p = new Remjobs_Payments();
$p->init();
remjobs_t_assert(shortcode_exists('remjobs_my_jobs'), 'my jobs shortcode registered via orchestrator init');
remjobs_t_assert(has_action('admin_post_remjobs_start_checkout') !== false, 'start checkout admin-post hooked via orchestrator init');
remjobs_t_assert(has_action('admin_post_remjobs_cancel_subscription') !== false, 'cancel subscription admin-post hooked via orchestrator init');

// Create a page containing the shortcode, point my_jobs_page_id at it, and
// confirm account_url resolves to it (end-to-end with the real setting).
$page_id = wp_insert_post(array(
    'post_type'   => 'page',
    'post_status' => 'publish',
    'post_title'  => 'My Jobs',
    'post_content' => '[remjobs_my_jobs]',
));
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('my_jobs_page_id' => $page_id)));
remjobs_t_assert(apply_filters('remjobs_account_url', home_url('/')) === get_permalink($page_id), 'account_url resolves to the configured My Jobs page');

echo "MY_JOBS_PAGE_URL:" . get_permalink($page_id) . "\n";
echo "MY_JOBS_PAGE_ID:" . $page_id . "\n";

remjobs_t_done();
