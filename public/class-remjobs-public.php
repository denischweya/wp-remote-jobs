<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 *
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/public
 */

declare(strict_types=1);

class Remjobs_Public {

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
     * @param    string    $plugin_name    The name of the plugin.
     * @param    string    $version        The version of this plugin.
     */
    public function __construct(string $plugin_name, string $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/remjobs-public.css',
            array(),
            $this->version,
            'all'
        );

        wp_enqueue_style(
            $this->plugin_name . '-select2',
            plugin_dir_url(__FILE__) . 'css/select2.min.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name . '-select2',
            plugin_dir_url(__FILE__) . 'js/select2.min.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/remjobs-public.js',
            array('jquery', $this->plugin_name . '-select2'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name,
            'remjobs_public',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('remjobs-public-nonce')
            )
        );
    }
}