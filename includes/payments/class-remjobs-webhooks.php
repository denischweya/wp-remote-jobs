<?php
/**
 * Webhook REST endpoints: one route per gateway, dispatching verified events
 * to the fulfillment service. This is the only path that marks orders paid.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Webhooks
{
    /** @var Remjobs_Payment_Gateway[] */
    private $gateways;

    /** @var Remjobs_Fulfillment */
    private $fulfillment;

    public function __construct(array $gateways, Remjobs_Fulfillment $fulfillment)
    {
        $this->gateways = $gateways;
        $this->fulfillment = $fulfillment;
    }

    public function register_routes(): void
    {
        register_rest_route('remjobs/v1', '/webhooks/(?P<gateway>[a-z]+)', array(
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'callback'            => array($this, 'handle'),
        ));
    }

    public function handle(WP_REST_Request $request)
    {
        $gid = $request->get_param('gateway');
        if (!isset($this->gateways[$gid])) {
            return new WP_REST_Response(array('ok' => false), 404);
        }
        $event = $this->gateways[$gid]->parse_webhook($request);
        if ($event === null) {
            return new WP_REST_Response(array('ok' => false), 400);
        } // signature/verification failure only

        // Signature-valid but unmapped/irrelevant event: acknowledge, no action.
        if (($event['kind'] ?? '') === 'ignored') {
            return new WP_REST_Response(array('ok' => true), 200);
        }

        // Event-id dedupe: a replayed or out-of-order redelivery of an
        // already-processed event is a no-op. Covers renew()/cancel() which
        // have no order-status guard of their own.
        $event_id = (string) ($event['event_id'] ?? '');
        $dedupe_key = $event_id !== '' ? 'remjobs_wh_' . $gid . '_' . $event_id : '';
        if ($dedupe_key !== '' && get_transient($dedupe_key)) {
            return new WP_REST_Response(array('ok' => true), 200);
        }

        switch ($event['kind']) {
            case 'payment_succeeded':
                if (!empty($event['order_id'])) {
                    $this->fulfillment->fulfill((int) $event['order_id'], $event);
                }
                break;
            case 'subscription_renewed':
                $this->fulfillment->renew($event);
                break;
            case 'subscription_canceled':
            case 'subscription_payment_failed':
                $this->fulfillment->cancel($event);
                break;
            case 'payment_refunded':
                $this->fulfillment->refund($event);
                break;
        }

        if ($dedupe_key !== '') {
            set_transient($dedupe_key, 1, DAY_IN_SECONDS);
        }

        return new WP_REST_Response(array('ok' => true), 200);
    }
}
