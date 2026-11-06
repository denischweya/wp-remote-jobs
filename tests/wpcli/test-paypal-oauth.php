<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-paypal.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'paypal_client_id' => 'client_abc',
    'paypal_secret'    => 'secret_xyz',
)));
delete_transient('remjobs_paypal_token');

$g = new Remjobs_Gateway_PayPal();
remjobs_t_assert($g->get_id() === 'paypal', 'get_id returns paypal');
remjobs_t_assert($g->is_configured() === true, 'is_configured true when client id + secret set');
remjobs_t_assert($g instanceof Remjobs_Payment_Gateway, 'implements gateway interface');

$calls = array();
add_filter('pre_http_request', function ($pre, $args, $url) use (&$calls) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        $calls[] = array('url' => $url, 'args' => $args);
        return array('response' => array('code' => 200), 'body' => json_encode(array(
            'access_token' => 'TOKEN_123',
            'expires_in'   => 3600,
        )));
    }
    return $pre;
}, 10, 3);

$ref = new ReflectionMethod('Remjobs_Gateway_PayPal', 'access_token');
$ref->setAccessible(true);

$token1 = $ref->invoke($g);
remjobs_t_assert($token1 === 'TOKEN_123', 'access_token returns the token from the response');
remjobs_t_assert(count($calls) === 1, 'first call hits the oauth endpoint');
remjobs_t_assert(strpos($calls[0]['url'], 'api-m.sandbox.paypal.com') !== false, 'sandbox mode uses sandbox base url');
remjobs_t_assert($calls[0]['args']['headers']['Authorization'] === 'Basic ' . base64_encode('client_abc:secret_xyz'), 'basic auth header built from client id + secret');
remjobs_t_assert($calls[0]['args']['body'] === 'grant_type=client_credentials', 'grant type body sent');

$token2 = $ref->invoke($g);
remjobs_t_assert($token2 === 'TOKEN_123', 'second call returns cached token');
remjobs_t_assert(count($calls) === 1, 'second call does not hit the network again (cached transient)');

// Live mode -> live base url
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'paypal_client_id' => 'client_abc',
    'paypal_secret'     => 'secret_xyz',
    'mode'              => 'live',
)));
delete_transient('remjobs_paypal_token');
$calls = array();
$ref->invoke($g);
remjobs_t_assert(strpos($calls[0]['url'], 'api-m.paypal.com') !== false && strpos($calls[0]['url'], 'sandbox') === false, 'live mode uses live base url');

// Missing config -> not configured
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_assert($g->is_configured() === false, 'is_configured false when keys blank');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
delete_transient('remjobs_paypal_token');
remjobs_t_done();
