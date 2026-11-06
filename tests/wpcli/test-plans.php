<?php
// tests/wpcli/test-plans.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-plans.php';

update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency' => 'USD', 'single_price' => '49.00', 'single_duration_days' => 30,
    'monthly_price' => '99.00', 'monthly_scope' => 'job',
)));

remjobs_t_assert(Remjobs_Plans::ids() === array('single', 'monthly'), 'plan ids');
remjobs_t_assert(Remjobs_Plans::exists('single') === true, 'single exists');
remjobs_t_assert(Remjobs_Plans::exists('nope') === false, 'unknown plan missing');

$single = Remjobs_Plans::get('single');
remjobs_t_assert($single['type'] === 'one_time', 'single is one_time');
remjobs_t_assert($single['duration_days'] === 30, 'single duration');
remjobs_t_assert(Remjobs_Plans::amount_minor('single') === 4900, 'single amount in cents');

$monthly = Remjobs_Plans::get('monthly');
remjobs_t_assert($monthly['type'] === 'subscription', 'monthly is subscription');
remjobs_t_assert($monthly['scope'] === 'job', 'monthly scope');
remjobs_t_assert(Remjobs_Plans::amount_minor('monthly') === 9900, 'monthly amount in cents');

// Zero-decimal currency: amount is the integer price, not x100.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency' => 'JPY', 'single_price' => '5000.00',
)));
remjobs_t_assert(Remjobs_Plans::amount_minor('single') === 5000, 'zero-decimal amount not multiplied');

// Restore clean defaults.
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

remjobs_t_done();
