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

// A lapsed featured job -> should expire
$lapsed = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'lapsed','post_author'=>1));
update_post_meta($lapsed, '_remjobs_featured', '1');
update_post_meta($lapsed, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() - 3600));

// A lapsed featured job PROTECTED by an active subscription -> must NOT expire
$prot = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'protected','post_author'=>1));
update_post_meta($prot, '_remjobs_featured', '1');
update_post_meta($prot, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() - 3600));
Remjobs_Subscriptions::upsert('stripe', 'sub_prot_'.$prot, array('user_id'=>1,'scope'=>'job','job_id'=>$prot,'status'=>'active','current_period_end'=>gmdate('Y-m-d H:i:s', time()+86400)));

// A future-dated featured job -> untouched
$future = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'publish','post_title'=>'future','post_author'=>1));
update_post_meta($future, '_remjobs_featured', '1');
update_post_meta($future, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 86400));

// A fresh awaiting-payment draft (not older than 48h) -> preserved by the sweep
$fresh_draft = wp_insert_post(array('post_type'=>'remjobs_jobs','post_status'=>'draft','post_title'=>'fresh awaiting','post_author'=>1));
update_post_meta($fresh_draft, '_remjobs_payment_status', 'awaiting');

$f->run_expiry_sweep();
remjobs_t_assert($f->is_featured($lapsed) === false, 'lapsed feature expired');
remjobs_t_assert($f->is_featured($prot) === true, 'subscription-protected feature kept');
remjobs_t_assert($f->is_featured($future) === true, 'future feature untouched');
remjobs_t_assert(get_post($fresh_draft) !== null, 'fresh awaiting draft preserved');

foreach (array($lapsed, $prot, $future, $fresh_draft) as $id) { wp_delete_post($id, true); }
global $wpdb; $wpdb->query("DELETE FROM " . $wpdb->prefix . "remjobs_subscriptions WHERE gateway_subscription_id LIKE 'sub_prot_%'");
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
