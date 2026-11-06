<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'class-remjobs-plans.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-paypal.php';

update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency'          => 'USD',
    'single_price'      => '49.00',
    'paypal_client_id'  => 'client_abc',
    'paypal_secret'     => 'secret_xyz',
)));
delete_transient('remjobs_paypal_token');

add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array(
            'access_token' => 'A', 'expires_in' => 3600,
        )));
    }
    if (strpos($url, '/v2/checkout/orders') !== false) {
        $GLOBALS['cap'] = json_decode($args['body'], true);
        return array('response' => array('code' => 201), 'body' => json_encode(array(
            'id'    => 'PPORDER1',
            'links' => array(array('rel' => 'approve', 'href' => 'https://paypal/approve/PPORDER1')),
        )));
    }
    if (strpos($url, '/v1/billing/subscriptions') !== false) {
        $GLOBALS['cap_sub'] = json_decode($args['body'], true);
        return array('response' => array('code' => 201), 'body' => json_encode(array(
            'id'    => 'PPSUB1',
            'links' => array(array('rel' => 'approve', 'href' => 'https://paypal/approve/PPSUB1')),
        )));
    }
    return $pre;
}, 10, 3);

$g = new Remjobs_Gateway_PayPal();

// One-time checkout (Orders v2)
$order = array('id' => 42, 'order_key' => 'rjk_abc', 'user_id' => 1, 'job_id' => 7, 'plan' => 'single', 'gateway' => 'paypal');
$url = $g->create_checkout($order, Remjobs_Plans::get('single'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url === 'https://paypal/approve/PPORDER1', 'one-time checkout returns approve url');
remjobs_t_assert($GLOBALS['cap']['intent'] === 'CAPTURE', 'order intent is CAPTURE');
remjobs_t_assert((string) $GLOBALS['cap']['purchase_units'][0]['custom_id'] === '42', 'custom_id carries order id');
remjobs_t_assert($GLOBALS['cap']['purchase_units'][0]['amount']['value'] === '49.00', 'amount value formatted');
remjobs_t_assert($GLOBALS['cap']['purchase_units'][0]['amount']['currency_code'] === 'USD', 'amount currency code');
remjobs_t_assert($GLOBALS['cap']['application_context']['return_url'] === 'https://site/return', 'return url passed');
remjobs_t_assert($GLOBALS['cap']['application_context']['cancel_url'] === 'https://site/cancel', 'cancel url passed');
remjobs_t_assert($GLOBALS['cap']['application_context']['user_action'] === 'PAY_NOW', 'user action PAY_NOW');

// Subscription checkout (Subscriptions v1) — plan id already cached, so no product/plan bootstrap calls needed.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency'                => 'USD',
    'monthly_price'           => '99.00',
    'paypal_client_id'        => 'client_abc',
    'paypal_secret'           => 'secret_xyz',
    'paypal_plan_id_monthly'  => 'PLAN_123',
)));
delete_transient('remjobs_paypal_token');

$order2 = array('id' => 99, 'order_key' => 'rjk_def', 'user_id' => 1, 'job_id' => 7, 'plan' => 'monthly', 'gateway' => 'paypal');
$url2 = $g->create_checkout($order2, Remjobs_Plans::get('monthly'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url2 === 'https://paypal/approve/PPSUB1', 'subscription checkout returns approve url');
remjobs_t_assert($GLOBALS['cap_sub']['plan_id'] === 'PLAN_123', 'uses cached monthly plan id (no bootstrap)');
remjobs_t_assert((string) $GLOBALS['cap_sub']['custom_id'] === '99', 'subscription custom_id is order id');
remjobs_t_assert($GLOBALS['cap_sub']['application_context']['user_action'] === 'SUBSCRIBE_NOW', 'user action SUBSCRIBE_NOW');

// Subscription checkout bootstraps product + plan when no cached plan id is set.
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency'          => 'USD',
    'monthly_price'     => '99.00',
    'paypal_client_id'  => 'client_abc',
    'paypal_secret'     => 'secret_xyz',
)));
delete_transient('remjobs_paypal_token');

$GLOBALS['cap_product'] = null;
$GLOBALS['cap_plan'] = null;
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/v1/catalogs/products') !== false) {
        $GLOBALS['cap_product'] = json_decode($args['body'], true);
        return array('response' => array('code' => 201), 'body' => json_encode(array('id' => 'PROD_1')));
    }
    if (strpos($url, '/v1/billing/plans') !== false) {
        $GLOBALS['cap_plan'] = json_decode($args['body'], true);
        return array('response' => array('code' => 201), 'body' => json_encode(array('id' => 'PLAN_NEW')));
    }
    return $pre;
}, 10, 3);

$order3 = array('id' => 100, 'order_key' => 'rjk_ghi', 'user_id' => 1, 'job_id' => 8, 'plan' => 'monthly', 'gateway' => 'paypal');
$url3 = $g->create_checkout($order3, Remjobs_Plans::get('monthly'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url3 === 'https://paypal/approve/PPSUB1', 'bootstrap subscription checkout returns approve url');
remjobs_t_assert(is_array($GLOBALS['cap_product']), 'product creation call made');
remjobs_t_assert($GLOBALS['cap_product']['type'] === 'SERVICE', 'product type SERVICE');
remjobs_t_assert(is_array($GLOBALS['cap_plan']), 'plan creation call made');
remjobs_t_assert($GLOBALS['cap_plan']['product_id'] === 'PROD_1', 'plan linked to created product');
remjobs_t_assert($GLOBALS['cap_plan']['billing_cycles'][0]['pricing_scheme']['fixed_price']['value'] === '99.00', 'plan pricing from server-side amount');
remjobs_t_assert($GLOBALS['cap_sub']['plan_id'] === 'PLAN_NEW', 'subscription created with the newly bootstrapped plan id');

$persisted = Remjobs_Payment_Settings::get('paypal_plan_id_monthly', '');
remjobs_t_assert($persisted === 'PLAN_NEW', 'bootstrapped plan id persisted to settings');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
delete_transient('remjobs_paypal_token');
remjobs_t_done();
