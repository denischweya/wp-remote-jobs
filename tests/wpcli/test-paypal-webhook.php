<?php
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

$GLOBALS['vstatus'] = 'SUCCESS';
$GLOBALS['captured_verify'] = null;
$GLOBALS['capture_calls'] = array();
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array(
            'access_token' => 'A', 'expires_in' => 3600,
        )));
    }
    if (strpos($url, '/v1/notifications/verify-webhook-signature') !== false) {
        $GLOBALS['captured_verify'] = json_decode($args['body'], true);
        return array('response' => array('code' => 200), 'body' => json_encode(array(
            'verification_status' => $GLOBALS['vstatus'],
        )));
    }
    if (strpos($url, '/capture') !== false) {
        $GLOBALS['capture_calls'][] = $url;
        return array('response' => array('code' => 201), 'body' => json_encode(array('id' => 'ORDER1', 'status' => 'COMPLETED')));
    }
    return $pre;
}, 10, 3);

function remjobs_t_paypal_request($body_array)
{
    $req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/paypal');
    $req->set_body(json_encode($body_array));
    $req->set_header('paypal_auth_algo', 'SHA256withRSA');
    $req->set_header('paypal_cert_url', 'https://api.paypal.com/cert');
    $req->set_header('paypal_transmission_id', 'TID-1');
    $req->set_header('paypal_transmission_sig', 'SIG-1');
    $req->set_header('paypal_transmission_time', '2026-11-06T10:00:00Z');
    return $req;
}

$g = new Remjobs_Gateway_PayPal();

// PAYMENT.CAPTURE.COMPLETED -> payment_succeeded
$req1 = remjobs_t_paypal_request(array(
    'id' => 'evt_1', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
    'resource' => array('id' => 'CAP1', 'custom_id' => '42'),
));
$evt1 = $g->parse_webhook($req1);
remjobs_t_assert(is_array($evt1), 'valid verification parses');
remjobs_t_assert($evt1['kind'] === 'payment_succeeded', 'PAYMENT.CAPTURE.COMPLETED mapped to payment_succeeded');
remjobs_t_assert($evt1['order_id'] === 42, 'order id extracted from custom_id');
remjobs_t_assert($evt1['gateway_order_id'] === 'CAP1', 'gateway_order_id from resource id');
remjobs_t_assert($evt1['subscription'] === null, 'no subscription payload for capture event');
remjobs_t_assert($evt1['event_id'] === 'evt_1', 'event id present');
remjobs_t_assert($GLOBALS['captured_verify']['webhook_id'] === 'WH-1', 'verify call includes configured webhook id');
remjobs_t_assert($GLOBALS['captured_verify']['auth_algo'] === 'SHA256withRSA', 'verify call includes auth_algo header value');
remjobs_t_assert($GLOBALS['captured_verify']['transmission_id'] === 'TID-1', 'verify call includes transmission id');

// BILLING.SUBSCRIPTION.ACTIVATED -> payment_succeeded with subscription payload
$req2 = remjobs_t_paypal_request(array(
    'id' => 'evt_2', 'event_type' => 'BILLING.SUBSCRIPTION.ACTIVATED',
    'resource' => array(
        'id' => 'SUB1', 'custom_id' => '43',
        'subscriber' => array('payer_id' => 'PAYER1'),
        'billing_info' => array('next_billing_time' => '2026-12-06T10:00:00Z'),
    ),
));
$evt2 = $g->parse_webhook($req2);
remjobs_t_assert($evt2['kind'] === 'payment_succeeded', 'BILLING.SUBSCRIPTION.ACTIVATED mapped to payment_succeeded');
remjobs_t_assert($evt2['order_id'] === 43, 'activation order id extracted');
remjobs_t_assert($evt2['gateway_order_id'] === 'SUB1', 'activation gateway_order_id is subscription id');
remjobs_t_assert(is_array($evt2['subscription']), 'activation has subscription payload');
remjobs_t_assert($evt2['subscription']['gateway_subscription_id'] === 'SUB1', 'subscription id captured');
remjobs_t_assert($evt2['subscription']['gateway_customer_id'] === 'PAYER1', 'payer id captured as customer id');
remjobs_t_assert($evt2['subscription']['current_period_end'] === gmdate('Y-m-d H:i:s', strtotime('2026-12-06T10:00:00Z')), 'current_period_end converted to UTC datetime');
remjobs_t_assert($evt2['subscription']['status'] === 'active', 'activation status active');

// PAYMENT.SALE.COMPLETED -> subscription_renewed
$req3 = remjobs_t_paypal_request(array(
    'id' => 'evt_3', 'event_type' => 'PAYMENT.SALE.COMPLETED',
    'resource' => array('id' => 'SALE1', 'billing_agreement_id' => 'SUB1'),
));
$evt3 = $g->parse_webhook($req3);
remjobs_t_assert($evt3['kind'] === 'subscription_renewed', 'PAYMENT.SALE.COMPLETED mapped to subscription_renewed');
remjobs_t_assert($evt3['order_id'] === null, 'renewal has no order id');
remjobs_t_assert($evt3['subscription']['gateway_subscription_id'] === 'SUB1', 'renewal subscription id from billing_agreement_id');
remjobs_t_assert($evt3['subscription']['current_period_end'] === null, 'renewal period end unknown -> null');

// BILLING.SUBSCRIPTION.CANCELLED -> subscription_canceled
$req4 = remjobs_t_paypal_request(array(
    'id' => 'evt_4', 'event_type' => 'BILLING.SUBSCRIPTION.CANCELLED',
    'resource' => array('id' => 'SUB2'),
));
$evt4 = $g->parse_webhook($req4);
remjobs_t_assert($evt4['kind'] === 'subscription_canceled', 'BILLING.SUBSCRIPTION.CANCELLED mapped to subscription_canceled');
remjobs_t_assert($evt4['subscription']['gateway_subscription_id'] === 'SUB2', 'cancel subscription id');

// BILLING.SUBSCRIPTION.EXPIRED / SUSPENDED -> subscription_canceled
foreach (array('BILLING.SUBSCRIPTION.EXPIRED', 'BILLING.SUBSCRIPTION.SUSPENDED') as $etype) {
    $reqX = remjobs_t_paypal_request(array('id' => 'evt_' . $etype, 'event_type' => $etype, 'resource' => array('id' => 'SUB3')));
    $evtX = $g->parse_webhook($reqX);
    remjobs_t_assert($evtX['kind'] === 'subscription_canceled', "$etype mapped to subscription_canceled");
}

// CHECKOUT.ORDER.APPROVED -> captures server-side, returns ignored
$req5 = remjobs_t_paypal_request(array(
    'id' => 'evt_5', 'event_type' => 'CHECKOUT.ORDER.APPROVED',
    'resource' => array('id' => 'ORDER1'),
));
$evt5 = $g->parse_webhook($req5);
remjobs_t_assert(is_array($evt5) && $evt5['kind'] === 'ignored', 'CHECKOUT.ORDER.APPROVED returns ignored (capture drives fulfillment)');
remjobs_t_assert($evt5['event_id'] === 'evt_5', 'approved event carries event id');
remjobs_t_assert(count($GLOBALS['capture_calls']) === 1, 'capture endpoint called for the approved order');
remjobs_t_assert(strpos($GLOBALS['capture_calls'][0], '/v2/checkout/orders/ORDER1/capture') !== false, 'capture called on the correct order id');

// Unmapped event type, verified -> ignored
$req6 = remjobs_t_paypal_request(array('id' => 'evt_6', 'event_type' => 'SOME.OTHER.EVENT', 'resource' => array()));
$evt6 = $g->parse_webhook($req6);
remjobs_t_assert(is_array($evt6) && $evt6['kind'] === 'ignored', 'unmapped verified event type returns ignored');
remjobs_t_assert($evt6['event_id'] === 'evt_6', 'ignored event carries event id');

// Verification failure -> null
$GLOBALS['vstatus'] = 'FAILURE';
$req7 = remjobs_t_paypal_request(array('id' => 'evt_7', 'event_type' => 'PAYMENT.CAPTURE.COMPLETED', 'resource' => array('id' => 'X', 'custom_id' => '1')));
remjobs_t_assert($g->parse_webhook($req7) === null, 'verification failure returns null');

// cancel_subscription
$GLOBALS['vstatus'] = 'SUCCESS';
$GLOBALS['cancel_calls'] = array();
add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/cancel') !== false) {
        $GLOBALS['cancel_calls'][] = $url;
        return array('response' => array('code' => 204), 'body' => '');
    }
    return $pre;
}, 10, 3);
$ok = $g->cancel_subscription('SUB1');
remjobs_t_assert($ok === true, 'cancel_subscription returns true on 2xx');
remjobs_t_assert(count($GLOBALS['cancel_calls']) === 1, 'cancel endpoint called once');
remjobs_t_assert(strpos($GLOBALS['cancel_calls'][0], '/v1/billing/subscriptions/SUB1/cancel') !== false, 'cancel called on correct subscription id');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
delete_transient('remjobs_paypal_token');
remjobs_t_done();
