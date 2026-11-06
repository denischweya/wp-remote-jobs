<?php
// tests/wpcli/test-ui-admin.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

$p = new Remjobs_Payments();
$p->init();
$ui = $p->ui();

remjobs_t_assert(method_exists($ui, 'add_orders_page'), 'has add_orders_page()');
remjobs_t_assert(method_exists($ui, 'render_orders_page'), 'has render_orders_page()');
remjobs_t_assert(method_exists($ui, 'add_job_columns'), 'has add_job_columns()');
remjobs_t_assert(method_exists($ui, 'render_job_column'), 'has render_job_column()');
remjobs_t_assert(has_action('admin_menu', array($ui, 'add_orders_page')) !== false, 'add_orders_page hooked to admin_menu');
remjobs_t_assert(has_filter('manage_remjobs_jobs_posts_columns', array($ui, 'add_job_columns')) !== false, 'add_job_columns hooked');
remjobs_t_assert(has_action('manage_remjobs_jobs_posts_custom_column', array($ui, 'render_job_column')) !== false, 'render_job_column hooked');

// columns
$cols = array('title' => 'Title', 'date' => 'Date');
$out = $ui->add_job_columns($cols);
remjobs_t_assert(isset($out['remjobs_featured'], $out['remjobs_expires'], $out['remjobs_payment']), 'job columns added');
remjobs_t_assert(isset($out['title'], $out['date']), 'existing columns preserved');

// new columns inserted before 'date'
$keys = array_keys($out);
$date_pos = array_search('date', $keys, true);
$featured_pos = array_search('remjobs_featured', $keys, true);
remjobs_t_assert($featured_pos !== false && $featured_pos < $date_pos, 'remjobs_featured column inserted before date column');

// column value: featured = Yes
$job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'col job', 'post_author' => 1));
update_post_meta($job, '_remjobs_featured', '1');
update_post_meta($job, '_remjobs_featured_expires', '2030-01-01 00:00:00');
update_post_meta($job, '_remjobs_payment_status', 'paid');

ob_start();
$ui->render_job_column('remjobs_featured', $job);
$v = ob_get_clean();
remjobs_t_assert(strpos($v, 'Yes') !== false, 'featured column shows Yes');

ob_start();
$ui->render_job_column('remjobs_expires', $job);
$v2 = ob_get_clean();
remjobs_t_assert(strpos($v2, '2030-01-01') !== false, 'expires column shows the expiry date');

ob_start();
$ui->render_job_column('remjobs_payment', $job);
$v3 = ob_get_clean();
remjobs_t_assert(strpos($v3, 'paid') !== false, 'payment column shows payment status');

// column value: not featured = em dash placeholder
$job2 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'col job 2', 'post_author' => 1));
ob_start();
$ui->render_job_column('remjobs_featured', $job2);
$v4 = ob_get_clean();
remjobs_t_assert(strpos($v4, 'Yes') === false, 'not-featured column does not show Yes');

wp_delete_post($job, true);
wp_delete_post($job2, true);

// render_orders_page: non-admin gets nothing, no fatal
wp_set_current_user(0);
ob_start();
$ui->render_orders_page();
$admin_out = ob_get_clean();
remjobs_t_assert($admin_out === '', 'render_orders_page outputs nothing without manage_options capability');

remjobs_t_done();
