<?php
require __DIR__ . '/_assert.php';
$base = WP_PLUGIN_DIR . '/remote-jobs/includes/payments/';
require_once $base . 'class-remjobs-payment-settings.php';
require_once $base . 'class-remjobs-plans.php';
require_once $base . 'interface-remjobs-gateway.php';
require_once $base . 'gateways/class-remjobs-gateway-stripe.php';
update_option('remjobs_payment_settings', array_merge(Remjobs_Payment_Settings::defaults(), array(
    'currency'=>'USD','single_price'=>'49.00','stripe_secret_key'=>'sk_test_x','stripe_price_id_monthly'=>'price_123',
)));

$captured = array();
add_filter('pre_http_request', function ($pre, $args, $url) use (&$captured) {
    $captured[] = array('url'=>$url, 'body'=>$args['body']);
    return array('response'=>array('code'=>200), 'body'=>json_encode(array('id'=>'cs_test_1','url'=>'https://checkout.stripe.com/c/cs_test_1')));
}, 10, 3);

$g = new Remjobs_Gateway_Stripe();
$order = array('id'=>42,'order_key'=>'rjk_abc','user_id'=>1,'job_id'=>7,'plan'=>'single','gateway'=>'stripe');
$url = $g->create_checkout($order, Remjobs_Plans::get('single'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url === 'https://checkout.stripe.com/c/cs_test_1', 'returns stripe checkout url');
remjobs_t_assert(strpos($captured[0]['url'], '/v1/checkout/sessions') !== false, 'calls sessions endpoint');
remjobs_t_assert($captured[0]['body']['mode'] === 'payment', 'one-time uses payment mode');
remjobs_t_assert((string)$captured[0]['body']['metadata']['order_id'] === '42', 'order id in metadata');
remjobs_t_assert((int)$captured[0]['body']['line_items'][0]['price_data']['unit_amount'] === 4900, 'amount in cents from server');

remjobs_t_assert($g->get_id() === 'stripe', 'get_id returns stripe');
remjobs_t_assert($g->is_configured() === true, 'is_configured true when secret key set');

// Subscription checkout: uses the cached monthly price id (no product/price creation calls needed).
$captured = array();
$order2 = array('id'=>99,'order_key'=>'rjk_def','user_id'=>1,'job_id'=>7,'plan'=>'monthly','gateway'=>'stripe');
$url2 = $g->create_checkout($order2, Remjobs_Plans::get('monthly'), 'https://site/return', 'https://site/cancel');
remjobs_t_assert($url2 === 'https://checkout.stripe.com/c/cs_test_1', 'subscription checkout returns url');
remjobs_t_assert($captured[0]['body']['mode'] === 'subscription', 'monthly uses subscription mode');
remjobs_t_assert($captured[0]['body']['line_items'][0]['price'] === 'price_123', 'uses cached monthly price id');
remjobs_t_assert((string)$captured[0]['body']['subscription_data']['metadata']['order_id'] === '99', 'subscription metadata carries order id');

update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
