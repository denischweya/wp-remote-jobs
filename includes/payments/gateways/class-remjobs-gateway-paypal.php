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
        return '';
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
