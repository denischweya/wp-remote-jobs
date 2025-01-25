<?php

/**
 * The core plugin class.
 *
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

declare(strict_types=1);

class Remjobs_Core
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Remjobs_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('REMJOBS_VERSION')) {
            $this->version = REMJOBS_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'remote-jobs';

        $this->remjobs_load_dependencies();
        $this->remjobs_set_locale();
        $this->remjobs_define_admin_hooks();
        $this->remjobs_define_public_hooks();
        $this->remjobs_register_post_types();
        $this->remjobs_register_taxonomies();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function remjobs_load_dependencies()
    {
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-loader.php';
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-i18n.php';
        require_once REMJOBS_PLUGIN_PATH . 'includes/class-remjobs-job.php';
        require_once REMJOBS_PLUGIN_PATH . 'admin/class-remjobs-admin.php';
        require_once REMJOBS_PLUGIN_PATH . 'public/class-remjobs-public.php';

        $this->loader = new Remjobs_Loader();
        new Remjobs_Job();
    }

    /**
     * Register custom post types and taxonomies
     *
     * @since    1.0.0
     */
    private function remjobs_register_post_types()
    {
        add_action('init', function() {
            $labels = array(
                'name'               => _x('Jobs', 'post type general name', 'remote-jobs'),
                'singular_name'      => _x('Job', 'post type singular name', 'remote-jobs'),
                'menu_name'          => _x('Jobs', 'admin menu', 'remote-jobs'),
                'name_admin_bar'     => _x('Job', 'add new on admin bar', 'remote-jobs'),
                'add_new'            => _x('Add New', 'job', 'remote-jobs'),
                'add_new_item'       => __('Add New Job', 'remote-jobs'),
                'new_item'           => __('New Job', 'remote-jobs'),
                'edit_item'          => __('Edit Job', 'remote-jobs'),
                'view_item'          => __('View Job', 'remote-jobs'),
                'all_items'          => __('All Jobs', 'remote-jobs'),
                'search_items'       => __('Search Jobs', 'remote-jobs'),
                'parent_item_colon'  => __('Parent Jobs:', 'remote-jobs'),
                'not_found'          => __('No jobs found.', 'remote-jobs'),
                'not_found_in_trash' => __('No jobs found in Trash.', 'remote-jobs')
            );

            $args = array(
                'labels'             => $labels,
                'description'        => __('Job listings.', 'remote-jobs'),
                'public'            => true,
                'publicly_queryable' => true,
                'show_ui'           => true,
                'show_in_menu'      => true,
                'query_var'         => true,
                'rewrite'           => array('slug' => 'jobs'),
                'capability_type'   => 'post',
                'has_archive'       => true,
                'hierarchical'      => false,
                'menu_position'     => null,
                'supports'          => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields'),
                'show_in_rest'      => true,
            );

            register_post_type('remjobs', $args);
        });
    }

    /**
     * Register custom taxonomies
     *
     * @since    1.0.0
     */
    private function remjobs_register_taxonomies()
    {
        add_action('init', function() {
            // Job Category Taxonomy
            register_taxonomy('remjobs_category', 'remjobs', array(
                'hierarchical' => true,
                'labels' => array(
                    'name' => _x('Job Categories', 'taxonomy general name', 'remote-jobs'),
                    'singular_name' => _x('Job Category', 'taxonomy singular name', 'remote-jobs'),
                    'menu_name' => __('Categories', 'remote-jobs'),
                ),
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'job-category'),
                'show_in_rest' => true,
            ));

            // Job Skills Taxonomy
            register_taxonomy('remjobs_skills', 'remjobs', array(
                'hierarchical' => false,
                'labels' => array(
                    'name' => _x('Skills', 'taxonomy general name', 'remote-jobs'),
                    'singular_name' => _x('Skill', 'taxonomy singular name', 'remote-jobs'),
                    'menu_name' => __('Skills', 'remote-jobs'),
                ),
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'job-skills'),
                'show_in_rest' => true,
            ));

            // Job Location Taxonomy
            register_taxonomy('remjobs_location', 'remjobs', array(
                'hierarchical' => true,
                'labels' => array(
                    'name' => _x('Locations', 'taxonomy general name', 'remote-jobs'),
                    'singular_name' => _x('Location', 'taxonomy singular name', 'remote-jobs'),
                    'menu_name' => __('Locations', 'remote-jobs'),
                ),
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => array('slug' => 'job-location'),
                'show_in_rest' => true,
            ));
        });
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since    1.0.0
     * @access   private
     */
    private function remjobs_set_locale()
    {
        $plugin_i18n = new Remjobs_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function remjobs_define_admin_hooks()
    {
        $plugin_admin = new Remjobs_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function remjobs_define_public_hooks()
    {
        $plugin_public = new Remjobs_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Remjobs_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader()
    {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version()
    {
        return $this->version;
    }
}
