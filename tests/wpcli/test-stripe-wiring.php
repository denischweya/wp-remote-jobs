<?php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payments.php';
$p = new Remjobs_Payments();
remjobs_t_assert(class_exists('Remjobs_Gateway_Stripe'), 'stripe gateway loaded');
remjobs_t_assert(class_exists('Remjobs_Fulfillment'), 'fulfillment loaded');
remjobs_t_assert(class_exists('Remjobs_Webhooks'), 'webhooks loaded');
remjobs_t_assert(interface_exists('Remjobs_Payment_Gateway'), 'gateway interface loaded');
remjobs_t_assert(method_exists($p, 'checkout'), 'checkout accessor present');
remjobs_t_assert($p->checkout() instanceof Remjobs_Checkout, 'checkout() returns a Remjobs_Checkout instance');
remjobs_t_done();
