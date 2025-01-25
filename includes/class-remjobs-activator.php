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

        // Flush rewrite rules after registering custom post types
        flush_rewrite_rules();
    }
}
