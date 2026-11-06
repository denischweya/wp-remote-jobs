<?php
// tests/wpcli/test-paypal-duplicate-approved.php — FIX I3: a duplicate/redelivered
// CHECKOUT.ORDER.APPROVED for an already-captured order must not throw (which
// would 500-loop forever since the capture runs before the handler's dedupe).
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'class-remjobs-plans.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-paypal.php';

update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'paypal_client_id'  => 'client_abc',
    'paypal_secret'     => 'secret_xyz',
    'paypal_webhook_id' => 'WH-1',
)));
delete_transient('remjobs_paypal_token');

$GLOBALS['capture_calls'] = array();
$GLOBALS['capture_response'] = null; // set per-scenario below
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array('access_token' => 'A', 'expires_in' => 3600)));
    }
    if (strpos($url, '/v1/notifications/verify-webhook-signature') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array('verification_status' => 'SUCCESS')));
    }
    if (strpos($url, '/capture') !== false) {
        $GLOBALS['capture_calls'][] = $url;
        return $GLOBALS['capture_response'];
    }
    return $pre;
}, 10, 3);

function remjobs_t_dup_paypal_request($body_array)
{
    $req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/paypal');
    $req->set_body(json_encode($body_array));
    $req->set_header('paypal_auth_algo', 'SHA256withRSA');
    $req->set_header('paypal_cert_url', 'https://api.paypal.com/cert');
    $req->set_header('paypal_transmission_id', 'TID-D');
    $req->set_header('paypal_transmission_sig', 'SIG-D');
    $req->set_header('paypal_transmission_time', '2026-11-06T10:00:00Z');
    return $req;
}

$g = new Remjobs_Gateway_PayPal();

// --- Duplicate APPROVED: capture endpoint returns 422 ORDER_ALREADY_CAPTURED -> treated as ignored, no exception ---
$GLOBALS['capture_response'] = array('response' => array('code' => 422), 'body' => json_encode(array(
    'name'    => 'UNPROCESSABLE_ENTITY',
    'message' => 'The requested action could not be performed, semantically incorrect, or failed business validation.',
    'details' => array(array('issue' => 'ORDER_ALREADY_CAPTURED', 'description' => 'The order has already been captured.')),
)));

$req1 = remjobs_t_dup_paypal_request(array(
    'id' => 'evt_dup_1', 'event_type' => 'CHECKOUT.ORDER.APPROVED',
    'resource' => array('id' => 'ORDER_DUP'),
));

$threw = false;
$evt1 = null;
try {
    $evt1 = $g->parse_webhook($req1);
} catch (Exception $e) {
    $threw = true;
}
remjobs_t_assert($threw === false, 'duplicate ORDER_ALREADY_CAPTURED does not throw an exception');
remjobs_t_assert(is_array($evt1) && $evt1['kind'] === 'ignored', 'duplicate ORDER_ALREADY_CAPTURED returns kind=ignored');
remjobs_t_assert($evt1['event_id'] === 'evt_dup_1', 'ignored duplicate-approved event carries event id');
remjobs_t_assert(count($GLOBALS['capture_calls']) === 1, 'capture endpoint was called once for the duplicate');

// --- Genuine/unexpected capture failure (e.g. INSTRUMENT_DECLINED) still throws, so PayPal retries ---
$GLOBALS['capture_calls'] = array();
$GLOBALS['capture_response'] = array('response' => array('code' => 422), 'body' => json_encode(array(
    'name'    => 'UNPROCESSABLE_ENTITY',
    'message' => 'The requested action could not be performed, semantically incorrect, or failed business validation.',
    'details' => array(array('issue' => 'INSTRUMENT_DECLINED', 'description' => 'The instrument was declined.')),
)));

$req2 = remjobs_t_dup_paypal_request(array(
    'id' => 'evt_dup_2', 'event_type' => 'CHECKOUT.ORDER.APPROVED',
    'resource' => array('id' => 'ORDER_DECLINED'),
));

$threw2 = false;
try {
    $g->parse_webhook($req2);
} catch (Exception $e) {
    $threw2 = true;
}
remjobs_t_assert($threw2 === true, 'a genuine/unexpected capture failure still throws (so PayPal retries)');

// --- Through the webhook handler: the duplicate-approved path returns HTTP 200, not 500 ---
require_once $base . 'class-remjobs-orders.php';
require_once $base . 'class-remjobs-subscriptions.php';
require_once $base . 'class-remjobs-featured.php';
require_once $base . 'class-remjobs-fulfillment.php';
require_once $base . 'class-remjobs-webhooks.php';
require_once WP_PLUGIN_DIR . '/remote-jobs/includes/class-remjobs-activator.php';
Remjobs_Activator::create_tables();

$featured = new Remjobs_Featured();
$fulfillment = new Remjobs_Fulfillment($featured);
$webhooks = new Remjobs_Webhooks(array('paypal' => $g), $fulfillment);

$GLOBALS['capture_calls'] = array();
$GLOBALS['capture_response'] = array('response' => array('code' => 422), 'body' => json_encode(array(
    'name'    => 'UNPROCESSABLE_ENTITY',
    'message' => 'The requested action could not be performed, semantically incorrect, or failed business validation.',
    'details' => array(array('issue' => 'ORDER_ALREADY_CAPTURED', 'description' => 'The order has already been captured.')),
)));

$req3 = remjobs_t_dup_paypal_request(array(
    'id' => 'evt_dup_3', 'event_type' => 'CHECKOUT.ORDER.APPROVED',
    'resource' => array('id' => 'ORDER_DUP3'),
));
$req3->set_param('gateway', 'paypal');

$resp3 = $webhooks->handle($req3);
remjobs_t_assert($resp3->get_status() === 200, 'webhook handler returns 200 (not 500) for a duplicate-approved redelivery');

delete_transient('remjobs_wh_paypal_evt_dup_3');
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
delete_transient('remjobs_paypal_token');
remjobs_t_done();
