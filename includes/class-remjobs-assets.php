<?php

/**
 * Handle all asset (JS/CSS) enqueuing for the plugin
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

declare(strict_types=1);

class Remjobs_Assets
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
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Register hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
        add_action('enqueue_block_assets', array($this, 'enqueue_block_assets'));
    }

    /**
     * Register and enqueue public-facing stylesheets.
     *
     * @since    1.0.0
     */
    public function enqueue_public_styles()
    {
        // Job List Block
        if (has_block('remjobs/job-list')) {
            wp_enqueue_style(
                'remjobs-job-list',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-list/build/style-index.css',
                array(),
                $this->version
            );
        }

        // Job Sidebar Block
        if (has_block('remjobs/job-sidebar')) {
            wp_enqueue_style(
                'remjobs-job-sidebar',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-sidebar/build/style-index.css',
                array(),
                $this->version
            );
        }

        // Submit Job Block
        if (has_block('remjobs/submit-job')) {
            wp_enqueue_style(
                'remjobs-submit-job',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/submit-job/build/style-index.css',
                array(),
                $this->version
            );
        }

        // Registration Block
        if (has_block('remjobs/registration')) {
            wp_enqueue_style(
                'remjobs-registration',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/registration/build/style-index.css',
                array(),
                $this->version
            );
        }
    }

    /**
     * Register and enqueue public-facing JavaScript files.
     *
     * @since    1.0.0
     */
    public function enqueue_public_scripts()
    {
        // Job List Block
        if (has_block('remjobs/job-list')) {
            $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/job-list/build/index.asset.php');

            wp_enqueue_script(
                'remjobs-job-list',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-list/build/index.js',
                $asset_file['dependencies'],
                $asset_file['version'],
                array('strategy' => 'defer')
            );

            wp_localize_script(
                'remjobs-job-list',
                'remjobsJobList',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('remjobs_filter_nonce'),
                )
            );
        }

        // Submit Job Block
        if (has_block('remjobs/submit-job')) {
            $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/submit-job/build/index.asset.php');

            wp_enqueue_script(
                'remjobs-submit-job',
                plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/submit-job/build/index.js',
                array_merge($asset_file['dependencies'], array('jquery')),
                $asset_file['version'],
                array('strategy' => 'defer')
            );

            wp_localize_script(
                'remjobs-submit-job',
                'remjobsSubmitJob',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('remjobs_submit_nonce'),
                )
            );
        }
    }

    /**
     * Register and enqueue admin-specific stylesheets.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_admin_styles($hook_suffix)
    {
        // Only load on job post type pages
        if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
            global $post_type;
            if ('jobs' === $post_type) {
                wp_enqueue_style(
                    'remjobs-admin',
                    plugin_dir_url(dirname(__FILE__)) . 'admin/css/remjobs-admin.css',
                    array(),
                    $this->version
                );
            }
        }
    }

    /**
     * Register and enqueue admin-specific JavaScript files.
     *
     * @since    1.0.0
     * @param    string    $hook_suffix    The current admin page.
     */
    public function enqueue_admin_scripts($hook_suffix)
    {
        // Only load on job post type pages
        if ('post.php' === $hook_suffix || 'post-new.php' === $hook_suffix) {
            global $post_type;
            if ('jobs' === $post_type) {
                wp_enqueue_script(
                    'remjobs-admin',
                    plugin_dir_url(dirname(__FILE__)) . 'admin/js/remjobs-admin.js',
                    array('jquery'),
                    $this->version,
                    array('strategy' => 'defer')
                );
            }
        }
    }

    /**
     * Register and enqueue block editor assets.
     *
     * @since    1.0.0
     */
    public function enqueue_block_editor_assets()
    {
        // Job List Block Editor
        $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/job-list/build/index.asset.php');
        wp_enqueue_script(
            'remjobs-job-list-editor',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-list/build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            array('strategy' => 'defer')
        );

        // Job Sidebar Block Editor
        $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/job-sidebar/build/index.asset.php');
        wp_enqueue_script(
            'remjobs-job-sidebar-editor',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-sidebar/build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            array('strategy' => 'defer')
        );

        // Submit Job Block Editor
        $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/submit-job/build/index.asset.php');
        wp_enqueue_script(
            'remjobs-submit-job-editor',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/submit-job/build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            array('strategy' => 'defer')
        );

        // Registration Block Editor
        $asset_file = include(plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/registration/build/index.asset.php');
        wp_enqueue_script(
            'remjobs-registration-editor',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/registration/build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            array('strategy' => 'defer')
        );
    }

    /**
     * Register and enqueue block assets for both editor and frontend.
     *
     * @since    1.0.0
     */
    public function enqueue_block_assets()
    {
        // Job List Block Styles
        wp_enqueue_style(
            'remjobs-job-list-style',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-list/build/style-index.css',
            array(),
            $this->version
        );

        // Job Sidebar Block Styles
        wp_enqueue_style(
            'remjobs-job-sidebar-style',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/job-sidebar/build/style-index.css',
            array(),
            $this->version
        );

        // Submit Job Block Styles
        wp_enqueue_style(
            'remjobs-submit-job-style',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/submit-job/build/style-index.css',
            array(),
            $this->version
        );

        // Registration Block Styles
        wp_enqueue_style(
            'remjobs-registration-style',
            plugin_dir_url(dirname(__FILE__)) . 'includes/blocks/registration/build/style-index.css',
            array(),
            $this->version
        );
    }
}
