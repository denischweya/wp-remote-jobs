<?php
// tests/wpcli/test-ui-submit.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/blocks/submit-job/submit-job.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

(new Remjobs_Payments())->init(); // registers $GLOBALS['remjobs_payments']

remjobs_t_assert(function_exists('remjobs_route_submitted_job'), 'remjobs_route_submitted_job exists');

// free plan: job stays pending, returns null
$j1 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'free job', 'post_author' => 1));
remjobs_t_assert(remjobs_route_submitted_job($j1, 'free', '', 1) === null, 'free plan not routed');
remjobs_t_assert(get_post_status($j1) === 'pending', 'free stays pending');

// empty plan behaves the same as free (default)
$j1b = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'default job', 'post_author' => 1));
remjobs_t_assert(remjobs_route_submitted_job($j1b, '', '', 1) === null, 'empty plan not routed');
remjobs_t_assert(get_post_status($j1b) === 'pending', 'empty plan stays pending');

// unknown plan string is also treated as not-paid (never trust client to invent a plan)
$j1c = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'bogus plan job', 'post_author' => 1));
remjobs_t_assert(remjobs_route_submitted_job($j1c, 'bogus', '', 1) === null, 'unknown plan not routed');
remjobs_t_assert(get_post_status($j1c) === 'pending', 'unknown plan stays pending');

// paid plan: job becomes draft+awaiting (checkout may throw if gateway unconfigured — catch and assert state)
$j2 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'paid job', 'post_author' => 1));
try {
    remjobs_route_submitted_job($j2, 'single', 'stripe', 1);
} catch (Exception $e) {
    // expected when the gateway isn't configured in this test environment
}
remjobs_t_assert(get_post_status($j2) === 'draft', 'paid job held as draft');
remjobs_t_assert(get_post_meta($j2, '_remjobs_payment_status', true) === 'awaiting', 'paid job awaiting');

wp_delete_post($j1, true);
wp_delete_post($j1b, true);
wp_delete_post($j1c, true);
wp_delete_post($j2, true);
remjobs_t_done();
