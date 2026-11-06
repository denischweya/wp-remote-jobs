<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Gateway_PayPal'), 'paypal gateway loaded');
$g = new Remjobs_Gateway_PayPal();
remjobs_t_assert($g->get_id() === 'paypal', 'paypal id');
remjobs_t_assert($g instanceof Remjobs_Payment_Gateway, 'implements interface');
remjobs_t_done();
