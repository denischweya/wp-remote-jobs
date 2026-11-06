<?php
/**
 * Contract implemented by each payment gateway (Stripe, PayPal, ...).
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

interface Remjobs_Payment_Gateway
{
    public function get_id(): string;                 // 'stripe' | 'paypal'
    public function is_configured(): bool;
    /** @return string hosted redirect URL; throws Exception on failure */
    public function create_checkout(array $order, array $plan, string $return_url, string $cancel_url): string;
    /**
     * Parse and verify an incoming webhook request.
     *
     * Return `null` ONLY on signature/verification failure (the handler maps
     * that to HTTP 400). For a signature-valid event the return is always a
     * normalized array (see plan): a mapped event with its `kind`, or
     * `array('kind' => 'ignored', 'event_id' => <id>)` for a valid-but-unmapped
     * event (the handler acknowledges those with HTTP 200 and no action).
     *
     * @return array|null normalized event; null only if unverified
     */
    public function parse_webhook(WP_REST_Request $request): ?array;
    public function cancel_subscription(string $gateway_subscription_id): bool;
}
