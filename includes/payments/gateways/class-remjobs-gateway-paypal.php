<?php
/**
 * PayPal gateway: hosted approval for one-time (Orders v2) and subscription
 * (Subscriptions v1) payments, plus API-based webhook signature verification
 * and event mapping, via the PayPal REST API (no SDK).
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Gateway_PayPal implements Remjobs_Payment_Gateway
{
    const BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';
    const BASE_LIVE = 'https://api-m.paypal.com';
    const TOKEN_TRANSIENT = 'remjobs_paypal_token';

    public function get_id(): string
    {
        return 'paypal';
    }

    public function is_configured(): bool
    {
        return (string) Remjobs_Payment_Settings::get('paypal_client_id', '') !== ''
            && (string) Remjobs_Payment_Settings::get('paypal_secret', '') !== '';
    }

    /** @return string hosted redirect URL; throws Exception on failure */
    public function create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string
    {
        $amount = number_format(
            (float) $plan['price'],
            in_array(strtoupper($plan['currency']), Remjobs_Plans::ZERO_DECIMAL, true) ? 0 : 2,
            '.',
            ''
        );

        if ($plan['type'] === 'one_time') {
            $response = $this->request('POST', '/v2/checkout/orders', array(
                'intent'              => 'CAPTURE',
                'purchase_units'      => array(array(
                    'custom_id'   => (string) $order['id'],
                    'description' => __('Featured job listing', 'remote-jobs'),
                    'amount'      => array(
                        'currency_code' => $plan['currency'],
                        'value'         => $amount,
                    ),
                )),
                'application_context' => array(
                    'return_url'         => $return_url,
                    'cancel_url'         => $cancel_url,
                    'user_action'        => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ),
            ));

            return $this->approve_link($response, __('PayPal order did not return an approval link.', 'remote-jobs'));
        }

        $plan_id = $this->ensure_plan_id($plan, $amount);

        $response = $this->request('POST', '/v1/billing/subscriptions', array(
            'plan_id'             => $plan_id,
            'custom_id'           => (string) $order['id'],
            'application_context' => array(
                'return_url'          => $return_url,
                'cancel_url'          => $cancel_url,
                'user_action'         => 'SUBSCRIBE_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ),
        ));

        return $this->approve_link($response, __('PayPal subscription did not return an approval link.', 'remote-jobs'));
    }

    /**
     * Ensure a billing plan id exists for the monthly plan, creating a
     * Product + Plan on first use and caching the plan id in settings.
     */
    private function ensure_plan_id(array $plan, string $amount): string
    {
        $cached = (string) Remjobs_Payment_Settings::get('paypal_plan_id_monthly', '');
        if ($cached !== '') {
            return $cached;
        }

        $product = $this->request('POST', '/v1/catalogs/products', array(
            'name' => __('Featured listing — monthly', 'remote-jobs'),
            'type' => 'SERVICE',
        ));
        if (empty($product['id'])) {
            throw new Exception(__('Could not create PayPal product.', 'remote-jobs'));
        }

        $billing_plan = $this->request('POST', '/v1/billing/plans', array(
            'product_id'     => (string) $product['id'],
            'billing_cycles' => array(array(
                'frequency'      => array(
                    'interval_unit'  => 'MONTH',
                    'interval_count' => 1,
                ),
                'tenure_type'    => 'REGULAR',
                'sequence'       => 1,
                'total_cycles'   => 0,
                'pricing_scheme' => array(
                    'fixed_price' => array(
                        'value'         => $amount,
                        'currency_code' => $plan['currency'],
                    ),
                ),
            )),
            'payment_preferences' => array(
                'auto_bill_outstanding' => true,
            ),
        ));
        if (empty($billing_plan['id'])) {
            throw new Exception(__('Could not create PayPal billing plan.', 'remote-jobs'));
        }

        $plan_id = (string) $billing_plan['id'];
        $settings = Remjobs_Payment_Settings::all();
        $settings['paypal_plan_id_monthly'] = $plan_id;
        update_option(Remjobs_Payment_Settings::OPTION, $settings);

        return $plan_id;
    }

    /** @return string the `href` of the `links` entry whose `rel` is `approve`; throws if absent */
    private function approve_link(array $response, string $error_message): string
    {
        $links = isset($response['links']) && is_array($response['links']) ? $response['links'] : array();
        foreach ($links as $link) {
            if (is_array($link) && ($link['rel'] ?? '') === 'approve' && !empty($link['href'])) {
                return (string) $link['href'];
            }
        }
        throw new Exception($error_message);
    }

    /**
     * @return array|null normalized event (see plan). `null` ONLY on
     *               signature/verification failure; a verified but
     *               unmapped event returns array('kind'=>'ignored', 'event_id'=>...).
     */
    public function parse_webhook(WP_REST_Request $request): ?array
    {
        $algo = (string) $request->get_header('paypal_auth_algo');
        $cert_url = (string) $request->get_header('paypal_cert_url');
        $transmission_id = (string) $request->get_header('paypal_transmission_id');
        $transmission_sig = (string) $request->get_header('paypal_transmission_sig');
        $transmission_time = (string) $request->get_header('paypal_transmission_time');

        $body = $request->get_body();
        $event = json_decode((string) $body, true);
        if (!is_array($event) || empty($event['id']) || empty($event['event_type'])) {
            return null;
        }

        try {
            $verify = $this->request('POST', '/v1/notifications/verify-webhook-signature', array(
                'auth_algo'         => $algo,
                'cert_url'          => $cert_url,
                'transmission_id'   => $transmission_id,
                'transmission_sig'  => $transmission_sig,
                'transmission_time' => $transmission_time,
                'webhook_id'        => (string) Remjobs_Payment_Settings::get('paypal_webhook_id', ''),
                'webhook_event'     => $event,
            ));
        } catch (Exception $e) {
            return null;
        }

        if (($verify['verification_status'] ?? '') !== 'SUCCESS') {
            return null;
        }

        $event_id = (string) $event['id'];
        $r = isset($event['resource']) && is_array($event['resource']) ? $event['resource'] : array();

        switch ($event['event_type']) {
            case 'CHECKOUT.ORDER.APPROVED':
                // Capture server-side; the resulting PAYMENT.CAPTURE.COMPLETED
                // event drives fulfillment. Let a capture failure throw so
                // PayPal retries the webhook delivery.
                $this->request('POST', '/v2/checkout/orders/' . rawurlencode((string) $r['id']) . '/capture', array());
                return array('kind' => 'ignored', 'event_id' => $event_id);

            case 'PAYMENT.CAPTURE.COMPLETED':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'payment_succeeded',
                    'order_id'         => isset($r['custom_id']) ? (int) $r['custom_id'] : null,
                    'gateway_order_id' => (string) ($r['id'] ?? ''),
                    'subscription'     => null,
                );

            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'payment_succeeded',
                    'order_id'         => isset($r['custom_id']) ? (int) $r['custom_id'] : null,
                    'gateway_order_id' => (string) ($r['id'] ?? ''),
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($r['id'] ?? ''),
                        'gateway_customer_id'     => (string) ($r['subscriber']['payer_id'] ?? ''),
                        'current_period_end'      => $this->paypal_date($r['billing_info']['next_billing_time'] ?? null),
                        'status'                  => 'active',
                    ),
                );

            case 'PAYMENT.SALE.COMPLETED':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'subscription_renewed',
                    'order_id'         => null,
                    'gateway_order_id' => (string) ($r['id'] ?? ''),
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($r['billing_agreement_id'] ?? ''),
                        'gateway_customer_id'     => '',
                        'current_period_end'      => null,
                        'status'                  => 'active',
                    ),
                );

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                return array(
                    'event_id'         => $event_id,
                    'kind'             => 'subscription_canceled',
                    'order_id'         => null,
                    'gateway_order_id' => '',
                    'subscription'     => array(
                        'gateway_subscription_id' => (string) ($r['id'] ?? ''),
                        'gateway_customer_id'     => '',
                        'current_period_end'      => null,
                        'status'                  => 'canceled',
                    ),
                );

            default:
                return array('kind' => 'ignored', 'event_id' => $event_id);
        }
    }

    /** Convert an ISO-8601 timestamp (e.g. `2026-12-06T10:00:00Z`) to `Y-m-d H:i:s` UTC, or null. */
    private function paypal_date(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }
        $ts = strtotime($iso);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : null;
    }

    public function cancel_subscription(string $gateway_subscription_id): bool
    {
        try {
            $this->request('POST', '/v1/billing/subscriptions/' . rawurlencode($gateway_subscription_id) . '/cancel', array(
                'reason' => __('Canceled by customer.', 'remote-jobs'),
            ));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    private function base_url(): string
    {
        return (string) Remjobs_Payment_Settings::get('mode', 'sandbox') === 'live'
            ? self::BASE_LIVE
            : self::BASE_SANDBOX;
    }

    /**
     * Cached OAuth access token (client-credentials grant). Cached in a
     * transient until `expires_in` minus a 60s safety margin.
     */
    private function access_token(): string
    {
        $cached = get_transient(self::TOKEN_TRANSIENT);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $client_id = (string) Remjobs_Payment_Settings::get('paypal_client_id', '');
        $secret = (string) Remjobs_Payment_Settings::get('paypal_secret', '');

        $response = wp_remote_post($this->base_url() . '/v1/oauth2/token', array(
            'method'  => 'POST',
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ),
            'body'    => 'grant_type=client_credentials',
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            $body = array();
        }

        if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
            $message = !empty($body['error_description'])
                ? (string) $body['error_description']
                : __('PayPal OAuth request failed.', 'remote-jobs');
            throw new Exception($message);
        }

        $token = (string) $body['access_token'];
        $ttl = isset($body['expires_in']) ? max(1, (int) $body['expires_in'] - 60) : 60;
        set_transient(self::TOKEN_TRANSIENT, $token, $ttl);

        return $token;
    }

    /**
     * Minimal HTTP helper: wp_remote_* with Bearer auth, JSON encode/decode,
     * timeout, and error normalization. Throws on transport error or HTTP >= 400.
     */
    private function request(string $method, string $path, array $body = null, bool $json = true): array
    {
        $args = array(
            'method'  => $method,
            'timeout' => 20,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token(),
            ),
        );

        if ($json) {
            $args['headers']['Content-Type'] = 'application/json';
            if ($body !== null) {
                $args['body'] = wp_json_encode($body);
            }
        } elseif ($body !== null) {
            $args['body'] = $body;
        }

        $url = $this->base_url() . $path;
        $response = ($method === 'GET')
            ? wp_remote_get($url, $args)
            : wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $raw = (string) wp_remote_retrieve_body($response);
        $decoded = $raw !== '' ? json_decode($raw, true) : array();
        if (!is_array($decoded)) {
            $decoded = array();
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code >= 400) {
            $message = !empty($decoded['message'])
                ? (string) $decoded['message']
                : (!empty($decoded['name']) ? (string) $decoded['name'] : __('PayPal request failed.', 'remote-jobs'));
            throw new Exception($message);
        }

        return $decoded;
    }
}
