<?php
// tests/wpcli/test-settings.php
require __DIR__ . '/_assert.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/payments/class-remjobs-payment-settings.php';

$d = Remjobs_Payment_Settings::defaults();
remjobs_t_assert($d['currency'] === 'USD', 'default currency USD');
remjobs_t_assert($d['single_price'] === '49.00', 'default single price');
remjobs_t_assert($d['single_duration_days'] === 30, 'default single duration');
remjobs_t_assert($d['monthly_scope'] === 'job', 'default monthly scope job');
remjobs_t_assert($d['expiry_behavior'] === 'unfeature', 'default expiry behavior');
remjobs_t_assert($d['mode'] === 'sandbox', 'default mode sandbox');

// Sanitize coerces/clamps.
$clean = Remjobs_Payment_Settings::sanitize(array(
    'currency' => 'usd', 'single_price' => '49', 'single_duration_days' => '45',
    'monthly_scope' => 'bogus', 'expiry_behavior' => 'unpublish', 'mode' => 'live',
    'monthly_account_cap' => '-3',
));
remjobs_t_assert($clean['currency'] === 'USD', 'currency upcased');
remjobs_t_assert($clean['single_price'] === '49.00', 'price normalized to 2dp');
remjobs_t_assert($clean['single_duration_days'] === 45, 'duration cast int');
remjobs_t_assert($clean['monthly_scope'] === 'job', 'invalid scope falls back');
remjobs_t_assert($clean['expiry_behavior'] === 'unpublish', 'valid expiry kept');
remjobs_t_assert($clean['monthly_account_cap'] === 0, 'negative cap clamped to 0');

// Constant override for secrets.
if (!defined('REMJOBS_STRIPE_SECRET')) { define('REMJOBS_STRIPE_SECRET', 'sk_from_const'); }
update_option('remjobs_payment_settings', array_merge($d, array('stripe_secret_key' => 'sk_from_db')));
remjobs_t_assert(Remjobs_Payment_Settings::get('stripe_secret_key') === 'sk_from_const', 'constant overrides db secret');
remjobs_t_assert(Remjobs_Payment_Settings::get('currency') === 'USD', 'non-secret read from db');

// Restore clean defaults so the dev site is not left with test values.
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

remjobs_t_done();
