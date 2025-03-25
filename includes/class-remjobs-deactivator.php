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

        // Schedule rewrite rules flush for after post types are unregistered
        add_action('init', array(__CLASS__, 'flush_rewrite_rules_on_deactivation'), 20);
    }

    /**
     * Flush rewrite rules after post types are unregistered
     *
     * @since    1.0.0
     */
    public static function flush_rewrite_rules_on_deactivation()
    {
        // This will run after the post types are unregistered
        flush_rewrite_rules();
    }
}
