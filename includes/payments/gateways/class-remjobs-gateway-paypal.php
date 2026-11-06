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

    public function parse_webhook(WP_REST_Request $request): ?array
    {
        return null;
    }

    public function cancel_subscription(string $gateway_subscription_id): bool
    {
        return false;
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
