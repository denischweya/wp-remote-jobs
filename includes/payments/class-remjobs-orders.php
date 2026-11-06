<?php
/**
 * Orders table store.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Orders
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'remjobs_orders';
    }

    public static function create(array $data): int
    {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');
        $row = array(
            'order_key'        => self::generate_key(),
            'user_id'          => (int) ($data['user_id'] ?? 0),
            'job_id'           => isset($data['job_id']) ? (int) $data['job_id'] : null,
            'plan'             => sanitize_text_field((string) ($data['plan'] ?? '')),
            'gateway'          => sanitize_text_field((string) ($data['gateway'] ?? '')),
            'gateway_order_id' => isset($data['gateway_order_id']) ? sanitize_text_field((string) $data['gateway_order_id']) : null,
            'subscription_id'  => isset($data['subscription_id']) ? (int) $data['subscription_id'] : null,
            'amount'           => number_format((float) ($data['amount'] ?? 0), 2, '.', ''),
            'currency'         => strtoupper(substr((string) ($data['currency'] ?? 'USD'), 0, 3)),
            'status'           => 'pending',
            'created_at'       => $now,
            'updated_at'       => $now,
        );
        $wpdb->insert(self::table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function get(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE id = %d', $id), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_key(string $key): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE order_key = %s', $key), ARRAY_A);
        return $row ?: null;
    }

    public static function get_by_gateway_order_id(string $gateway, string $gateway_order_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_order_id = %s', $gateway, $gateway_order_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function update(int $id, array $fields): bool
    {
        global $wpdb;
        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        return false !== $wpdb->update(self::table(), $fields, array('id' => $id));
    }

    /**
     * Mark an order paid. Idempotent: returns true without rewriting if already paid.
     */
    public static function mark_paid(int $id, string $gateway_order_id = '', string $event_id = ''): bool
    {
        $order = self::get($id);
        if (!$order) {
            return false;
        }
        if ($order['status'] === 'paid') {
            return true;
        }
        $fields = array('status' => 'paid');
        if ($gateway_order_id !== '') {
            $fields['gateway_order_id'] = $gateway_order_id;
        }
        if ($event_id !== '') {
            $fields['gateway_event_id'] = $event_id;
        }
        return self::update($id, $fields);
    }

    private static function generate_key(): string
    {
        return 'rjk_' . wp_generate_password(20, false, false);
    }
}
