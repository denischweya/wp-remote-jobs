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
    /** @return array|null normalized event (see plan); null if unverified/irrelevant */
    public function parse_webhook(WP_REST_Request $request): ?array;
    public function cancel_subscription(string $gateway_subscription_id): bool;
}
