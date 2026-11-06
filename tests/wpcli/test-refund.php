<?php
// tests/wpcli/test-refund.php — FIX I1: refund/chargeback webhooks expire the feature.
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/';
require_once $base . 'class-remjobs-activator.php';
require_once $base . 'payments/class-remjobs-payment-settings.php';
require_once $base . 'payments/class-remjobs-plans.php';
require_once $base . 'payments/class-remjobs-orders.php';
require_once $base . 'payments/class-remjobs-subscriptions.php';
require_once $base . 'payments/class-remjobs-featured.php';
require_once $base . 'payments/class-remjobs-fulfillment.php';
require_once $base . 'payments/interface-remjobs-gateway.php';
require_once $base . 'payments/class-remjobs-webhooks.php';
require_once $base . 'payments/gateways/class-remjobs-gateway-stripe.php';
require_once $base . 'payments/gateways/class-remjobs-gateway-paypal.php';
Remjobs_Activator::create_tables();
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'stripe_webhook_secret' => 'whsec_test',
    'paypal_client_id'      => 'client_abc',
    'paypal_secret'         => 'secret_xyz',
    'paypal_webhook_id'     => 'WH-1',
)));
delete_transient('remjobs_paypal_token');

$featured = new Remjobs_Featured();
$fulfillment = new Remjobs_Fulfillment($featured);

// --- Stripe: charge.refunded with metadata.order_id -> payment_refunded, fulfillment.refund() ---
$job1 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'refund job (stripe)', 'post_author' => 1));
update_post_meta($job1, '_remjobs_featured', '1');
update_post_meta($job1, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
$order1_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job1, 'plan' => 'single', 'gateway' => 'stripe', 'amount' => '49.00', 'currency' => 'USD'));
Remjobs_Orders::mark_paid($order1_id, 'ch_1', 'evt_paid_1');

$stripe = new Remjobs_Gateway_Stripe();
$refund_body = json_encode(array(
    'id'   => 'evt_refund_1',
    'type' => 'charge.refunded',
    'data' => array('object' => array('id' => 'ch_1', 'metadata' => array('order_id' => (string) $order1_id))),
));
$t = time();
$sig = hash_hmac('sha256', $t . '.' . $refund_body, 'whsec_test');
$req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$req->set_body($refund_body);
$req->set_header('stripe_signature', "t={$t},v1={$sig}");

$evt = $stripe->parse_webhook($req);
remjobs_t_assert(is_array($evt), 'stripe charge.refunded parses with valid signature');
remjobs_t_assert($evt['kind'] === 'payment_refunded', 'stripe charge.refunded mapped to payment_refunded');
remjobs_t_assert($evt['order_id'] === $order1_id, 'stripe refund order_id extracted from charge metadata');

$fulfillment->refund($evt);
$order1 = Remjobs_Orders::get($order1_id);
remjobs_t_assert($order1['status'] === 'refunded', 'stripe-refunded order marked refunded');
remjobs_t_assert($featured->is_featured($job1) === false, 'stripe refund unfeatures the job');

// Idempotent: calling refund() again on an already-refunded order is a no-op.
update_post_meta($job1, '_remjobs_featured', '1'); // simulate re-featuring to detect a retroactive re-expire
$fulfillment->refund($evt);
remjobs_t_assert($featured->is_featured($job1) === true, 'refund() is a no-op once the order is already refunded (idempotent guard)');

// --- Stripe: charge.refunded with no order_id -> ignored ---
$unresolvable_body = json_encode(array(
    'id'   => 'evt_refund_unresolvable',
    'type' => 'charge.refunded',
    'data' => array('object' => array('id' => 'ch_unknown', 'metadata' => array())),
));
$t2 = time();
$sig2 = hash_hmac('sha256', $t2 . '.' . $unresolvable_body, 'whsec_test');
$req2 = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stripe');
$req2->set_body($unresolvable_body);
$req2->set_header('stripe_signature', "t={$t2},v1={$sig2}");
$evt2 = $stripe->parse_webhook($req2);
remjobs_t_assert(is_array($evt2) && $evt2['kind'] === 'ignored', 'stripe refund with no attributable order_id returns ignored');
remjobs_t_assert($evt2['event_id'] === 'evt_refund_unresolvable', 'ignored refund still carries event id');

// --- PayPal: PAYMENT.CAPTURE.REFUNDED with custom_id -> payment_refunded, fulfillment.refund() ---
$job2 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'refund job (paypal)', 'post_author' => 1));
update_post_meta($job2, '_remjobs_featured', '1');
update_post_meta($job2, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
$order2_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job2, 'plan' => 'single', 'gateway' => 'paypal', 'amount' => '49.00', 'currency' => 'USD'));
Remjobs_Orders::mark_paid($order2_id, 'CAP2', 'evt_paid_2');

add_filter('pre_http_request', function ($pre, $args, $url) {
    if (strpos($url, '/v1/oauth2/token') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array('access_token' => 'A', 'expires_in' => 3600)));
    }
    if (strpos($url, '/v1/notifications/verify-webhook-signature') !== false) {
        return array('response' => array('code' => 200), 'body' => json_encode(array('verification_status' => 'SUCCESS')));
    }
    return $pre;
}, 10, 3);

function remjobs_t_refund_paypal_request($body_array)
{
    $req = new WP_REST_Request('POST', '/remjobs/v1/webhooks/paypal');
    $req->set_body(json_encode($body_array));
    $req->set_header('paypal_auth_algo', 'SHA256withRSA');
    $req->set_header('paypal_cert_url', 'https://api.paypal.com/cert');
    $req->set_header('paypal_transmission_id', 'TID-R');
    $req->set_header('paypal_transmission_sig', 'SIG-R');
    $req->set_header('paypal_transmission_time', '2026-11-06T10:00:00Z');
    return $req;
}

$paypal = new Remjobs_Gateway_PayPal();
$pp_req1 = remjobs_t_refund_paypal_request(array(
    'id' => 'evt_pp_refund_1', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
    'resource' => array('id' => 'REF1', 'custom_id' => (string) $order2_id),
));
$pp_evt1 = $paypal->parse_webhook($pp_req1);
remjobs_t_assert(is_array($pp_evt1), 'paypal PAYMENT.CAPTURE.REFUNDED parses with valid verification');
remjobs_t_assert($pp_evt1['kind'] === 'payment_refunded', 'paypal refund mapped to payment_refunded');
remjobs_t_assert($pp_evt1['order_id'] === $order2_id, 'paypal refund order_id extracted from custom_id');

$fulfillment->refund($pp_evt1);
$order2 = Remjobs_Orders::get($order2_id);
remjobs_t_assert($order2['status'] === 'refunded', 'paypal-refunded order marked refunded');
remjobs_t_assert($featured->is_featured($job2) === false, 'paypal refund unfeatures the job');

// --- PayPal: PAYMENT.CAPTURE.REFUNDED with no custom_id -> ignored ---
$pp_req2 = remjobs_t_refund_paypal_request(array(
    'id' => 'evt_pp_refund_unresolvable', 'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
    'resource' => array('id' => 'REF2'),
));
$pp_evt2 = $paypal->parse_webhook($pp_req2);
remjobs_t_assert(is_array($pp_evt2) && $pp_evt2['kind'] === 'ignored', 'paypal refund with no custom_id returns ignored');
remjobs_t_assert($pp_evt2['event_id'] === 'evt_pp_refund_unresolvable', 'ignored paypal refund still carries event id');

// --- End-to-end through the webhook handler: payment_refunded routes to fulfillment->refund() ---
$job3 = wp_insert_post(array('post_type' => 'remjobs_jobs', 'post_status' => 'publish', 'post_title' => 'refund job (handler)', 'post_author' => 1));
update_post_meta($job3, '_remjobs_featured', '1');
update_post_meta($job3, '_remjobs_featured_expires', gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS));
$order3_id = Remjobs_Orders::create(array('user_id' => 1, 'job_id' => $job3, 'plan' => 'single', 'gateway' => 'stripe', 'amount' => '49.00', 'currency' => 'USD'));
Remjobs_Orders::mark_paid($order3_id, 'ch_3', 'evt_paid_3');

$gateway_stub = new class implements Remjobs_Payment_Gateway {
    public $next = null;
    public function get_id(): string { return 'stub'; }
    public function is_configured(): bool { return true; }
    public function create_checkout(array $o, array $p, string $r, string $c): string { return ''; }
    public function parse_webhook(WP_REST_Request $request): ?array { return $this->next; }
    public function cancel_subscription(string $id): bool { return true; }
};
$webhooks = new Remjobs_Webhooks(array('stub' => $gateway_stub), $fulfillment);

$gateway_stub->next = array('kind' => 'payment_refunded', 'event_id' => 'evt_handler_refund', 'order_id' => $order3_id);
$req3 = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stub');
$req3->set_param('gateway', 'stub');
$resp3 = $webhooks->handle($req3);
remjobs_t_assert($resp3->get_status() === 200, 'handler returns 200 for payment_refunded event');
$order3 = Remjobs_Orders::get($order3_id);
remjobs_t_assert($order3['status'] === 'refunded', 'handler routes payment_refunded to fulfillment->refund()');
remjobs_t_assert($featured->is_featured($job3) === false, 'handler-driven refund unfeatures the job');

delete_transient('remjobs_wh_stub_evt_handler_refund');
foreach (array($job1, $job2, $job3) as $id) {
    wp_delete_post($id, true);
}
global $wpdb;
$wpdb->query("DELETE FROM " . Remjobs_Orders::table() . " WHERE id IN ({$order1_id}, {$order2_id}, {$order3_id})");
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
delete_transient('remjobs_paypal_token');
remjobs_t_done();
