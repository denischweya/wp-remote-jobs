<?php
/**
 * Subscriptions table store.
 *
 * @package Remote_Jobs
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_Subscriptions
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'remjobs_subscriptions';
    }

    /**
     * Insert or update by (gateway, gateway_subscription_id). Returns the row id.
     */
    public static function upsert(string $gateway, string $gateway_subscription_id, array $data): int
    {
        global $wpdb;
        $existing = self::get_by_gateway_id($gateway, $gateway_subscription_id);
        $now = gmdate('Y-m-d H:i:s');

        $fields = array();
        foreach (array('user_id', 'scope', 'job_id', 'gateway_customer_id', 'status', 'current_period_end', 'cancel_at_period_end') as $k) {
            if (array_key_exists($k, $data)) {
                $fields[$k] = $data[$k];
            }
        }

        if ($existing) {
            $fields['updated_at'] = $now;
            if (!empty($fields)) {
                $wpdb->update(self::table(), $fields, array('id' => (int) $existing['id']));
            }
            return (int) $existing['id'];
        }

        $row = array(
            'user_id'                 => (int) ($data['user_id'] ?? 0),
            'scope'                   => sanitize_text_field((string) ($data['scope'] ?? 'job')),
            'job_id'                  => isset($data['job_id']) ? (int) $data['job_id'] : null,
            'gateway'                 => $gateway,
            'gateway_subscription_id' => $gateway_subscription_id,
            'gateway_customer_id'     => isset($data['gateway_customer_id']) ? sanitize_text_field((string) $data['gateway_customer_id']) : null,
            'status'                  => sanitize_text_field((string) ($data['status'] ?? 'active')),
            'current_period_end'      => $data['current_period_end'] ?? null,
            'cancel_at_period_end'    => (int) ($data['cancel_at_period_end'] ?? 0),
            'created_at'              => $now,
            'updated_at'              => $now,
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

    public static function get_by_gateway_id(string $gateway, string $gateway_subscription_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_subscription_id = %s', $gateway, $gateway_subscription_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function set_status(int $id, string $status): bool
    {
        global $wpdb;
        return false !== $wpdb->update(
            self::table(),
            array('status' => sanitize_text_field($status), 'updated_at' => gmdate('Y-m-d H:i:s')),
            array('id' => $id)
        );
    }

    public static function active_for_job(int $job_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE job_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $job_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Lookup by gateway_subscription_id only (no gateway label needed) — used
     * when a webhook payload doesn't conveniently carry which gateway it's from.
     */
    public static function get_by_gateway_id_any(string $gateway_subscription_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE gateway_subscription_id = %s LIMIT 1', $gateway_subscription_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function active_account_for_user(int $user_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . self::table() . " WHERE user_id = %d AND scope = 'account' AND status = 'active' ORDER BY id DESC LIMIT 1", $user_id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public static function active_count_for_user(int $user_id): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . " WHERE user_id = %d AND status = 'active'", $user_id)
        );
    }
}
