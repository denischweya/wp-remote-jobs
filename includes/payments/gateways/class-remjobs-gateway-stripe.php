<?php
/**
 * Stripe gateway: hosted Checkout Sessions (one-time + subscription) and
 * signature-verified webhooks, via the Stripe REST API (no SDK).
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Gateway_Stripe implements Remjobs_Payment_Gateway
{
    const API_BASE = 'https://api.stripe.com';

    public function get_id(): string
    {
        return 'stripe';
    }

    public function is_configured(): bool
    {
        return (string) Remjobs_Payment_Settings::get('stripe_secret_key', '') !== '';
    }

    /** @return string hosted redirect URL; throws Exception on failure */
    public function create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string
    {
        $params = array(
            'success_url'         => $return_url,
            'cancel_url'          => $cancel_url,
            'client_reference_id' => (string) $order['order_key'],
            'metadata'            => array('order_id' => (string) $order['id']),
        );

        if ($plan['type'] === 'one_time') {
            $params['mode'] = 'payment';
            $params['line_items'] = array(array(
                'quantity'   => 1,
                'price_data' => array(
                    'currency'     => strtolower($plan['currency']),
                    'unit_amount'  => Remjobs_Plans::amount_minor('single'),
                    'product_data' => array('name' => __('Featured job listing', 'remote-jobs')),
                ),
            ));
        } else {
            $params['mode'] = 'subscription';
            $price_id = (string) Remjobs_Payment_Settings::get('stripe_price_id_monthly', '');
            if ($price_id === '') {
                $price_id = $this->ensure_monthly_price($plan);
            }
            $params['line_items'] = array(array(
                'quantity' => 1,
                'price'    => $price_id,
            ));
            $params['subscription_data'] = array('metadata' => array('order_id' => (string) $order['id']));
        }

        $response = $this->request('POST', '/v1/checkout/sessions', $params);

        if (empty($response['url'])) {
            throw new Exception(
                !empty($response['error']['message'])
                    ? (string) $response['error']['message']
                    : __('Stripe checkout session did not return a URL.', 'remote-jobs')
            );
        }

        return (string) $response['url'];
    }

    /**
     * Create a Product + recurring monthly Price for the plan, and persist
     * the price id back into settings so it's only created once.
     */
    private function ensure_monthly_price(array $plan): string
    {
        $product = $this->request('POST', '/v1/products', array(
            'name' => __('Featured listing — monthly', 'remote-jobs'),
        ));
        if (empty($product['id'])) {
            throw new Exception(
                !empty($product['error']['message'])
                    ? (string) $product['error']['message']
                    : __('Could not create Stripe product.', 'remote-jobs')
            );
        }

        $price = $this->request('POST', '/v1/prices', array(
            'unit_amount'       => Remjobs_Plans::amount_minor('monthly'),
            'currency'          => strtolower($plan['currency']),
            'recurring'         => array('interval' => 'month'),
            'product'           => (string) $product['id'],
        ));
        if (empty($price['id'])) {
            throw new Exception(
                !empty($price['error']['message'])
                    ? (string) $price['error']['message']
                    : __('Could not create Stripe price.', 'remote-jobs')
            );
        }

        $price_id = (string) $price['id'];
        $settings = Remjobs_Payment_Settings::all();
        $settings['stripe_price_id_monthly'] = $price_id;
        update_option(Remjobs_Payment_Settings::OPTION, $settings);

        return $price_id;
    }

    /** @return array|null normalized event (see plan); null if unverified/irrelevant */
    public function parse_webhook(WP_REST_Request $request): ?array
    {
        $payload = $request->get_body();
        $sig = (string) $request->get_header('stripe_signature');
        $secret = (string) Remjobs_Payment_Settings::get('stripe_webhook_secret', '');

        $t = null;
        $v1 = null;
        foreach (explode(',', $sig) as $part) {
            $part = trim($part);
            if (strpos($part, 't=') === 0) {
                $t = substr($part, 2);
            } elseif (strpos($part, 'v1=') === 0) {
                $v1 = substr($part, 3);
            }
        }
        if ($t === null || $v1 === null || $secret === '') {
            return null;
        }

        $signed = $t . '.' . $payload;
        $expected = hash_hmac('sha256', $signed, $secret);
        if (!hash_equals($expected, $v1)) {
            return null;
        }
        if (abs(time() - (int) $t) > 300) {
            return null;
        }

        $event = json_decode((string) $payload, true);
        if (!is_array($event) || empty($event['id']) || empty($event['type'])) {
            return null;
        }

        $event_id = (string) $event['id'];
        $obj = isset($event['data']['object']) && is_array($event['data']['object']) ? $event['data']['object'] : array();

        switch ($event['type']) {
            case 'checkout.session.completed':
                $order_id = isset($obj['metadata']['order_id']) ? (int) $obj['metadata']['order_id'] : null;
                $gateway_order_id = (string) ($obj['id'] ?? '');
                $subscription = null;
                if (($obj['mode'] ?? '') === 'subscription' && !empty($obj['subscription'])) {
                    $sub_data = $this->get_subscription((string) $obj['subscription']);
                    $subscription = array(
                        'gateway_subscription_id' => (string) $obj['subscription'],
                        'gateway_customer_id'     => (string) ($sub_data['customer'] ?? ''),
                        'current_period_end'      => isset($sub_data['current_period_end']) ? gmdate('Y-m-d H:i:s', (int) $sub_data['current_period_end']) : null,
                        'status'                  => 'active',
                    );
                }
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'payment_succeeded',
                    'order_id'         => $order_id,
                    'gateway_order_id' => $gateway_order_id,
                    'subscription'     => $subscription,
                );

            case 'invoice.paid':
                $period_end = $obj['lines']['data'][0]['period']['end'] ?? null;
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'subscription_renewed',
                    'order_id'         => null,
                    'gateway_order_id' => (string) ($obj['id'] ?? ''),
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($obj['subscription'] ?? ''),
                        'gateway_customer_id'     => (string) ($obj['customer'] ?? ''),
                        'current_period_end'      => $period_end !== null ? gmdate('Y-m-d H:i:s', (int) $period_end) : null,
                        'status'                  => 'active',
                    ),
                );

            case 'invoice.payment_failed':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'subscription_payment_failed',
                    'order_id'         => null,
                    'gateway_order_id' => (string) ($obj['id'] ?? ''),
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($obj['subscription'] ?? ''),
                        'gateway_customer_id'     => (string) ($obj['customer'] ?? ''),
                        'current_period_end'      => null,
                        'status'                  => 'past_due',
                    ),
                );

            case 'customer.subscription.deleted':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'subscription_canceled',
                    'order_id'         => null,
                    'gateway_order_id' => '',
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($obj['id'] ?? ''),
                        'gateway_customer_id'     => (string) ($obj['customer'] ?? ''),
                        'current_period_end'      => null,
                        'status'                  => 'canceled',
                    ),
                );

            default:
                return null;
        }
    }

    private function get_subscription(string $gateway_subscription_id): array
    {
        return $this->request('GET', '/v1/subscriptions/' . rawurlencode($gateway_subscription_id));
    }

    public function cancel_subscription(string $gateway_subscription_id): bool
    {
        try {
            $this->request('POST', '/v1/subscriptions/' . rawurlencode($gateway_subscription_id), array(
                'cancel_at_period_end' => 'true',
            ));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Minimal HTTP helper: wp_remote_post/get with Bearer auth, form-encoded
     * body, JSON decode, and error normalization. Throws on transport error.
     */
    private function request(string $method, string $path, array $params = array()): array
    {
        $secret = (string) Remjobs_Payment_Settings::get('stripe_secret_key', '');
        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret,
            ),
        );
        if (!empty($params)) {
            $args['body'] = $params;
        }

        $url = self::API_BASE . $path;
        $response = ($method === 'GET')
            ? wp_remote_get($url, $args)
            : wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = array();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            $message = !empty($body['error']['message']) ? (string) $body['error']['message'] : __('Stripe request failed.', 'remote-jobs');
            throw new Exception($message);
        }

        return $body;
    }
}
