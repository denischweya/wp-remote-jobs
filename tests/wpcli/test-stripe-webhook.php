<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-stripe.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array('stripe_webhook_secret'=>'whsec_test')));

$body = json_encode(array('id'=>'evt_1','type'=>'checkout.session.completed','data'=>array('object'=>array(
    'id'=>'cs_1','mode'=>'payment','metadata'=>array('order_id'=>'42')))));
$t = time();
$sig = hash_hmac('sha256', $t . '.' . $body, 'whsec_test');
$req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$req->set_body($body);
$req->set_header('stripe_signature', "t={$t},v1={$sig}");

$g = new Remjobs_Gateway_Stripe();
$evt = $g->parse_webhook($req);
remjobs_t_assert(is_array($evt), 'valid signature parses');
remjobs_t_assert($evt['kind'] === 'payment_succeeded', 'kind mapped');
remjobs_t_assert($evt['order_id'] === 42, 'order id extracted');
remjobs_t_assert($evt['event_id'] === 'evt_1', 'event id');
remjobs_t_assert($evt['gateway_order_id'] === 'cs_1', 'gateway_order_id extracted');
remjobs_t_assert($evt['subscription'] === null, 'payment mode has no subscription payload');

// Tampered signature -> rejected
$req->set_header('stripe_signature', "t={$t},v1=deadbeef");
remjobs_t_assert($g->parse_webhook($req) === null, 'bad signature rejected');

// Stale timestamp -> rejected
$old_t = time() - 400;
$old_sig = hash_hmac('sha256', $old_t . '.' . $body, 'whsec_test');
$req->set_header('stripe_signature', "t={$old_t},v1={$old_sig}");
remjobs_t_assert($g->parse_webhook($req) === null, 'stale timestamp rejected');

// checkout.session.completed in subscription mode -> fetches the subscription for period_end/customer
$sub_body = json_encode(array('id'=>'evt_2','type'=>'checkout.session.completed','data'=>array('object'=>array(
    'id'=>'cs_2','mode'=>'subscription','subscription'=>'sub_abc','metadata'=>array('order_id'=>'43')))));
$sub_t = time();
$sub_sig = hash_hmac('sha256', $sub_t . '.' . $sub_body, 'whsec_test');
$sub_req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$sub_req->set_body($sub_body);
$sub_req->set_header('stripe_signature', "t={$sub_t},v1={$sub_sig}");

$period_end_unix = time() + 30*86400;
add_filter('pre_http_request', function ($pre, $args, $url) use ($period_end_unix) {
    if (strpos($url, '/v1/subscriptions/sub_abc') !== false) {
        return array('response'=>array('code'=>200), 'body'=>json_encode(array(
            'id'=>'sub_abc','customer'=>'cus_abc','current_period_end'=>$period_end_unix,
        )));
    }
    return $pre;
}, 10, 3);

$sub_evt = $g->parse_webhook($sub_req);
remjobs_t_assert(is_array($sub_evt), 'subscription-mode session parses');
remjobs_t_assert($sub_evt['kind'] === 'payment_succeeded', 'subscription-mode kind mapped');
remjobs_t_assert($sub_evt['order_id'] === 43, 'subscription-mode order id extracted');
remjobs_t_assert(is_array($sub_evt['subscription']), 'subscription payload present');
remjobs_t_assert($sub_evt['subscription']['gateway_subscription_id'] === 'sub_abc', 'subscription id captured');
remjobs_t_assert($sub_evt['subscription']['gateway_customer_id'] === 'cus_abc', 'customer id captured');
remjobs_t_assert($sub_evt['subscription']['current_period_end'] === gmdate('Y-m-d H:i:s', $period_end_unix), 'period end converted to UTC datetime');
remjobs_t_assert($sub_evt['subscription']['status'] === 'active', 'subscription status active');

// invoice.paid -> subscription_renewed
$renew_period_end = time() + 60*86400;
$renew_body = json_encode(array('id'=>'evt_3','type'=>'invoice.paid','data'=>array('object'=>array(
    'subscription'=>'sub_xyz','lines'=>array('data'=>array(array('period'=>array('end'=>$renew_period_end)))),
))));
$renew_t = time();
$renew_sig = hash_hmac('sha256', $renew_t . '.' . $renew_body, 'whsec_test');
$renew_req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$renew_req->set_body($renew_body);
$renew_req->set_header('stripe_signature', "t={$renew_t},v1={$renew_sig}");
$renew_evt = $g->parse_webhook($renew_req);
remjobs_t_assert($renew_evt['kind'] === 'subscription_renewed', 'invoice.paid mapped to subscription_renewed');
remjobs_t_assert($renew_evt['order_id'] === null, 'renewal has no order id');
remjobs_t_assert($renew_evt['subscription']['gateway_subscription_id'] === 'sub_xyz', 'renewal subscription id');
remjobs_t_assert($renew_evt['subscription']['current_period_end'] === gmdate('Y-m-d H:i:s', $renew_period_end), 'renewal period end converted');

// invoice.payment_failed -> subscription_payment_failed
$failed_body = json_encode(array('id'=>'evt_4','type'=>'invoice.payment_failed','data'=>array('object'=>array('subscription'=>'sub_fail'))));
$failed_t = time();
$failed_sig = hash_hmac('sha256', $failed_t . '.' . $failed_body, 'whsec_test');
$failed_req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$failed_req->set_body($failed_body);
$failed_req->set_header('stripe_signature', "t={$failed_t},v1={$failed_sig}");
$failed_evt = $g->parse_webhook($failed_req);
remjobs_t_assert($failed_evt['kind'] === 'subscription_payment_failed', 'invoice.payment_failed mapped');
remjobs_t_assert($failed_evt['subscription']['gateway_subscription_id'] === 'sub_fail', 'failed payment subscription id');

// customer.subscription.deleted -> subscription_canceled
$del_body = json_encode(array('id'=>'evt_5','type'=>'customer.subscription.deleted','data'=>array('object'=>array('id'=>'sub_del'))));
$del_t = time();
$del_sig = hash_hmac('sha256', $del_t . '.' . $del_body, 'whsec_test');
$del_req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$del_req->set_body($del_body);
$del_req->set_header('stripe_signature', "t={$del_t},v1={$del_sig}");
$del_evt = $g->parse_webhook($del_req);
remjobs_t_assert($del_evt['kind'] === 'subscription_canceled', 'customer.subscription.deleted mapped');
remjobs_t_assert($del_evt['subscription']['gateway_subscription_id'] === 'sub_del', 'canceled subscription id');

// Unknown event type -> null
$unk_body = json_encode(array('id'=>'evt_6','type'=>'charge.refunded','data'=>array('object'=>array())));
$unk_t = time();
$unk_sig = hash_hmac('sha256', $unk_t . '.' . $unk_body, 'whsec_test');
$unk_req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$unk_req->set_body($unk_body);
$unk_req->set_header('stripe_signature', "t={$unk_t},v1={$unk_sig}");
remjobs_t_assert($g->parse_webhook($unk_req) === null, 'unknown event type returns null');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
