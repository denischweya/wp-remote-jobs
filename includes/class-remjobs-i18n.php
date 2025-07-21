<?php

declare(strict_types=1);

/**
 * Define the internationalization functionality.
 *
 * For plugins hosted on WordPress.org, translations are automatically loaded
 * by WordPress core. This class is maintained for backward compatibility.
 *
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class Remjobs_i18n
{
    /**
     * Initialize the internationalization features.
     *
     * Note: As of WordPress 4.6+, plugins hosted on WordPress.org
     * no longer need to call load_plugin_textdomain() as translations
     * are automatically loaded by WordPress core.
     *
     * @since    1.0.0
     */
    public function load_plugin_textdomain()
    {
        // This method is kept for backward compatibility
        // No action needed as WordPress.org automatically loads translations
    }
}
