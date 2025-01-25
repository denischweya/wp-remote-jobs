<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 *
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/admin
 */

declare(strict_types=1);

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples of hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/admin
 * @author     Denis Fedorov
 */
class Remjobs_Admin
{
    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles(): void
    {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/remjobs-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts(): void
    {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/remjobs-admin.js',
            array('jquery'),
            $this->version,
            false
        );
    }
}
