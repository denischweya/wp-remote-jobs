<?php
// tests/wpcli/test-ui-myjobs.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';

update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'enabled_gateways' => array('stripe'),
    'stripe_secret_key' => 'sk',
    'stripe_publishable_key' => 'pk',
)));

$p = new Remjobs_Payments();
$p->init();
$ui = $p->ui();

$user_id = 1;
wp_set_current_user($user_id);

$featured_job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'My Featured Job', 'post_author' => $user_id));
update_post_meta($featured_job, '_remjobs_featured', '1');
update_post_meta($featured_job, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 10 * DAY_IN_SECONDS));
update_post_meta($featured_job, '_remjobs_feature_plan', 'single');

$plain_job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'pending', 'post_title' => 'My Plain Job', 'post_author' => $user_id));

$html = $ui->render_my_jobs(array());

remjobs_t_assert(strpos($html, 'My Featured Job') !== false, 'output contains featured job title');
remjobs_t_assert(strpos($html, 'My Plain Job') !== false, 'output contains plain job title');
remjobs_t_assert(strpos($html, 'Featured') !== false, 'output contains Featured status text');
remjobs_t_assert(strpos($html, '<form') !== false, 'output contains a form');
remjobs_t_assert(strpos($html, 'remjobs_start_checkout') !== false, 'output contains the start-checkout action/nonce');
remjobs_t_assert(strpos($html, '_wpnonce') !== false, 'output contains a nonce field');

// subscription-featured job -> cancel subscription form + renewal date
$sub_job = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'My Sub Job', 'post_author' => $user_id));
update_post_meta($sub_job, '_remjobs_featured', '1');
update_post_meta($sub_job, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS));
update_post_meta($sub_job, '_remjobs_feature_plan', 'monthly');
update_post_meta($sub_job, '_remjobs_feature_subscription_id', 999);

$html2 = $ui->render_my_jobs(array());
remjobs_t_assert(strpos($html2, 'remjobs_cancel_subscription') !== false, 'output contains cancel-subscription action/nonce for subscription-backed job');

// Not logged in: login prompt, no Feature form.
wp_set_current_user(0);
$html3 = $ui->render_my_jobs(array());
remjobs_t_assert(strpos($html3, 'remjobs_start_checkout') === false, 'logged-out output has no Feature form');
remjobs_t_assert(stripos($html3, 'log in') !== false || stripos($html3, 'login') !== false || stripos($html3, 'logged in') !== false, 'logged-out output contains a login prompt');

wp_set_current_user($user_id);
wp_delete_post($featured_job, true);
wp_delete_post($plain_job, true);
wp_delete_post($sub_job, true);
wp_set_current_user(0);
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

remjobs_t_done();
