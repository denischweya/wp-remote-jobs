<?php
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
Remjobs_Activator::create_tables();
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());

// A fulfillment spy: counts how many times renew()/cancel()/fulfill() run.
class Remjobs_Fulfillment_Spy extends Remjobs_Fulfillment {
    public $fulfill_calls = 0;
    public $renew_calls = 0;
    public $cancel_calls = 0;
    public function __construct() {}
    public function fulfill(int $order_id, array $event): void { $this->fulfill_calls++; }
    public function renew(array $event): void { $this->renew_calls++; }
    public function cancel(array $event): void { $this->cancel_calls++; }
}

// A gateway stub whose parse_webhook returns whatever we queue up.
$gateway = new class implements Remjobs_Payment_Gateway {
    public $next = null; // the event to return (or null)
    public function get_id(): string { return 'stub'; }
    public function is_configured(): bool { return true; }
    public function create_checkout(array $o, array $p, string $r, string $c): string { return ''; }
    public function parse_webhook(WP_REST_Request $request): ?array { return $this->next; }
    public function cancel_subscription(string $id): bool { return true; }
};

$spy = new Remjobs_Fulfillment_Spy();
$webhooks = new Remjobs_Webhooks(array('stub' => $gateway), $spy);

// Clear any lingering transients from prior runs of this test.
delete_transient('remjobs_wh_stub_wh_renew_1');
delete_transient('remjobs_wh_stub_wh_cancel_1');

function remjobs_wh_req() {
    $r = new WP_REST_Request('POST', '/remjobs/v1/webhooks/stub');
    $r->set_param('gateway', 'stub');
    return $r;
}

// --- Unknown gateway -> 404 ---
$bad = new WP_REST_Request('POST', '/remjobs/v1/webhooks/nope');
$bad->set_param('gateway', 'nope');
remjobs_t_assert($webhooks->handle($bad)->get_status() === 404, 'unknown gateway returns 404');

// --- Bad signature (parse_webhook null) -> 400 ---
$gateway->next = null;
remjobs_t_assert($webhooks->handle(remjobs_wh_req())->get_status() === 400, 'null (bad signature) returns 400');

// --- I3: signature-valid but unmapped event -> 200, no action ---
$gateway->next = array('kind' => 'ignored', 'event_id' => 'evt_ignored');
$resp = $webhooks->handle(remjobs_wh_req());
remjobs_t_assert($resp->get_status() === 200, 'ignored event returns 200 (not 400)');
remjobs_t_assert($spy->renew_calls === 0 && $spy->cancel_calls === 0 && $spy->fulfill_calls === 0, 'ignored event triggers no fulfillment');

// --- I1: replayed renewal event -> processed once, second call is a no-op ---
$gateway->next = array('kind' => 'subscription_renewed', 'event_id' => 'wh_renew_1', 'subscription' => array('gateway_subscription_id' => 'sub_r'));
remjobs_t_assert($webhooks->handle(remjobs_wh_req())->get_status() === 200, 'first renewal returns 200');
remjobs_t_assert($spy->renew_calls === 1, 'renew() called once on first delivery');
remjobs_t_assert($webhooks->handle(remjobs_wh_req())->get_status() === 200, 'replayed renewal returns 200');
remjobs_t_assert($spy->renew_calls === 1, 'renew() NOT called again on replay (event-id dedupe)');

// --- I1: replayed cancel event -> processed once ---
$gateway->next = array('kind' => 'subscription_canceled', 'event_id' => 'wh_cancel_1', 'subscription' => array('gateway_subscription_id' => 'sub_c'));
$webhooks->handle(remjobs_wh_req());
$webhooks->handle(remjobs_wh_req());
remjobs_t_assert($spy->cancel_calls === 1, 'cancel() deduped across replays');

// --- An event with an EMPTY event_id must not dedupe-crash and still routes ---
$gateway->next = array('kind' => 'subscription_renewed', 'event_id' => '', 'subscription' => array('gateway_subscription_id' => 'sub_e'));
$before = $spy->renew_calls;
remjobs_t_assert($webhooks->handle(remjobs_wh_req())->get_status() === 200, 'empty event_id still returns 200');
remjobs_t_assert($spy->renew_calls === $before + 1, 'empty event_id routes (no dedupe guard crash)');

delete_transient('remjobs_wh_stub_wh_renew_1');
delete_transient('remjobs_wh_stub_wh_cancel_1');
update_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
remjobs_t_done();
