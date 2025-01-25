<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

declare(strict_types=1);

class Remjobs_Deactivator
{
    /**
     * Clean up plugin data and settings
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        // Remove plugin options
        delete_option('remjobs_settings');

        // Flush rewrite rules after deregistering custom post types
        flush_rewrite_rules();
    }
}
