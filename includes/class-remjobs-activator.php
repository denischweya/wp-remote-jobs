<?php

/**
 * Fired during plugin activation
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

declare(strict_types=1);

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Remjobs_Activator
{
    /**
     * Initialize plugin settings and create necessary database tables
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        // Create plugin options with default values
        add_option('remjobs_settings', array(
            'jobs_per_page' => 10,
            'enable_application_tracking' => true,
            'notification_email' => get_option('admin_email'),
        ));

        // Create the payments tables.
        self::create_tables();

        // Seed default payment settings on first activation.
        self::seed_payment_settings();

        // Schedule a one-time action to flush rewrite rules
        add_action('init', array(__CLASS__, 'flush_rewrite_rules_on_activation'), 20);
    }

    /**
     * Seed the payment settings option with defaults on first activation.
     *
     * @since 1.1.0
     */
    public static function seed_payment_settings(): void
    {
        require_once REMJOBS_PLUGIN_PATH . 'includes/payments/class-remjobs-payment-settings.php';
        if (false === get_option('remjobs_payment_settings')) {
            add_option('remjobs_payment_settings', Remjobs_Payment_Settings::defaults());
        }
    }

    /**
     * Create the custom tables used by the payments subsystem.
     *
     * Safe to run repeatedly: dbDelta only applies the diff, and the schema
     * version is stored so the orchestrator can self-heal on upgrades.
     *
     * @since 1.1.0
     */
    public static function create_tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $orders = $wpdb->prefix . 'remjobs_orders';
        $subs   = $wpdb->prefix . 'remjobs_subscriptions';

        $sql_orders = "CREATE TABLE {$orders} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_key VARCHAR(32) NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            job_id BIGINT UNSIGNED NULL,
            plan VARCHAR(20) NOT NULL,
            gateway VARCHAR(20) NOT NULL,
            gateway_order_id VARCHAR(255) NULL,
            gateway_event_id VARCHAR(255) NULL,
            subscription_id BIGINT UNSIGNED NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            currency VARCHAR(3) NOT NULL DEFAULT 'USD',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_key (order_key),
            KEY user_id (user_id),
            KEY job_id (job_id),
            KEY gateway_order_id (gateway_order_id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_subs = "CREATE TABLE {$subs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            scope VARCHAR(10) NOT NULL DEFAULT 'job',
            job_id BIGINT UNSIGNED NULL,
            gateway VARCHAR(20) NOT NULL,
            gateway_subscription_id VARCHAR(255) NOT NULL,
            gateway_customer_id VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            current_period_end DATETIME NULL,
            cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY gateway_subscription_id (gateway_subscription_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql_orders);
        dbDelta($sql_subs);
        update_option('remjobs_db_version', REMJOBS_DB_VERSION);
    }

    /**
     * Flush rewrite rules after post types are registered
     *
     * @since    1.0.0
     */
    public static function flush_rewrite_rules_on_activation()
    {
        // This will run after the post types are registered (which happens at priority 10)
        flush_rewrite_rules();
    }
}
