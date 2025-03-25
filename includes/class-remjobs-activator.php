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

        // Schedule a one-time action to flush rewrite rules
        add_action('init', array(__CLASS__, 'flush_rewrite_rules_on_activation'), 20);
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
