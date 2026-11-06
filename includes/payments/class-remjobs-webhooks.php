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
        } // bad signature / irrelevant

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
        }
        return new WP_REST_Response(array('ok' => true), 200);
    }
}
