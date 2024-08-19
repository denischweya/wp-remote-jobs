<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://https://denis.swishfolio.com/
 * @since      1.0.0
 *
 * @package    Wp_Remote_Jobs
 * @subpackage Wp_Remote_Jobs/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wp_Remote_Jobs
 * @subpackage Wp_Remote_Jobs/includes
 * @author     Denis Bosire <denischweya@gmail.com>
 */
class Wp_Remote_Jobs
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Wp_Remote_Jobs_Loader    $loader    Maintains and registers all hooks for the plugin.
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
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        if (defined('WP_REMOTE_JOBS_VERSION')) {
            $this->version = WP_REMOTE_JOBS_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'wp-remote-jobs';

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Wp_Remote_Jobs_Loader. Orchestrates the hooks of the plugin.
     * - Wp_Remote_Jobs_i18n. Defines internationalization functionality.
     * - Wp_Remote_Jobs_Admin. Defines all hooks for the admin area.
     * - Wp_Remote_Jobs_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-remote-jobs-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-wp-remote-jobs-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-wp-remote-jobs-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-wp-remote-jobs-public.php';

        $this->loader = new Wp_Remote_Jobs_Loader();

    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Wp_Remote_Jobs_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale()
    {

        $plugin_i18n = new Wp_Remote_Jobs_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');

    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks()
    {

        $plugin_admin = new Wp_Remote_Jobs_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

        // Add hook for registering custom post type
        $this->loader->add_action('init', $this, 'register_jobs_post_type');

        // Add hook for registering custom taxonomies and fields
        $this->loader->add_action('init', $this, 'register_job_taxonomies_and_fields');

    }

    /**
     * Register custom post type for jobs
     *
     * @since    1.0.0
     */
    public function register_jobs_post_type()
    {
        $labels = array(
            'name'               => _x('Jobs', 'post type general name', 'wp-remote-jobs'),
            'singular_name'      => _x('Job', 'post type singular name', 'wp-remote-jobs'),
            'menu_name'          => _x('Jobs', 'admin menu', 'wp-remote-jobs'),
            'name_admin_bar'     => _x('Job', 'add new on admin bar', 'wp-remote-jobs'),
            'add_new'            => _x('Add New', 'job', 'wp-remote-jobs'),
            'add_new_item'       => __('Add New Job', 'wp-remote-jobs'),
            'new_item'           => __('New Job', 'wp-remote-jobs'),
            'edit_item'          => __('Edit Job', 'wp-remote-jobs'),
            'view_item'          => __('View Job', 'wp-remote-jobs'),
            'all_items'          => __('All Jobs', 'wp-remote-jobs'),
            'search_items'       => __('Search Jobs', 'wp-remote-jobs'),
            'parent_item_colon'  => __('Parent Jobs:', 'wp-remote-jobs'),
            'not_found'          => __('No jobs found.', 'wp-remote-jobs'),
            'not_found_in_trash' => __('No jobs found in Trash.', 'wp-remote-jobs')
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'jobs' ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => null,
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments' ),
            'show_in_rest'       => true, // Enable REST API
        );

        register_post_type('jobs', $args);
    }

    /**
     * Register custom taxonomies and fields for the jobs post type
     *
     * @since    1.0.0
     */
    public function register_job_taxonomies_and_fields()
    {
        // Register Job Category taxonomy
        register_taxonomy('job_category', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Job Categories', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Job Category', 'taxonomy singular name', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-category'),
        ));

        // Register Skills taxonomy
        register_taxonomy('job_skills', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Skills', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Skill', 'taxonomy singular name', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-skills'),
        ));

        // Register Location taxonomy
        register_taxonomy('job_location', 'jobs', array(
            'hierarchical' => false,
            'labels' => array(
                'name' => _x('Locations', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Location', 'taxonomy singular name', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-location'),
        ));

        // Register Employment Type taxonomy
        register_taxonomy('employment_type', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Employment Types', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Employment Type', 'taxonomy singular name', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'employment-type'),
        ));

        // Register Benefits taxonomy
        register_taxonomy('job_benefits', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Benefits', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Benefit', 'taxonomy singular name', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-benefits'),
        ));

        // Add custom fields
        add_action('add_meta_boxes', array($this, 'add_job_meta_boxes'));
        add_action('save_post_jobs', array($this, 'save_job_meta'));
    }

    /**
     * Add meta boxes for custom fields
     */
    public function add_job_meta_boxes()
    {
        add_meta_box(
            'job_details',
            __('Job Details', 'wp-remote-jobs'),
            array($this, 'render_job_meta_box'),
            'jobs',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     */
    public function render_job_meta_box($post)
    {
        wp_nonce_field('job_meta_box', 'job_meta_box_nonce');

        $job_title = get_post_meta($post->ID, '_job_title', true);
        $worldwide = get_post_meta($post->ID, '_worldwide', true);
        $salary_min = get_post_meta($post->ID, '_salary_min', true);
        $salary_max = get_post_meta($post->ID, '_salary_max', true);
        $how_to_apply = get_post_meta($post->ID, '_how_to_apply', true);

        echo '<p><label for="job_title">' . __('Job Title', 'wp-remote-jobs') . '</label><br>';
        echo '<input type="text" id="job_title" name="job_title" value="' . esc_attr($job_title) . '" size="25" /></p>';

        echo '<p><label>' . __('Is position open worldwide?', 'wp-remote-jobs') . '</label><br>';
        echo '<input type="radio" id="worldwide_yes" name="worldwide" value="yes" ' . checked($worldwide, 'yes', false) . '>';
        echo '<label for="worldwide_yes">Yes</label>';
        echo '<input type="radio" id="worldwide_no" name="worldwide" value="no" ' . checked($worldwide, 'no', false) . '>';
        echo '<label for="worldwide_no">No</label></p>';

        echo '<p><label>' . __('Salary Range', 'wp-remote-jobs') . '</label><br>';
        echo '<input type="number" id="salary_min" name="salary_min" value="' . esc_attr($salary_min) . '" placeholder="Min" />';
        echo ' - ';
        echo '<input type="number" id="salary_max" name="salary_max" value="' . esc_attr($salary_max) . '" placeholder="Max" /></p>';

        echo '<p><label for="how_to_apply">' . __('How to Apply', 'wp-remote-jobs') . '</label><br>';
        echo '<textarea id="how_to_apply" name="how_to_apply" rows="5" cols="50">' . esc_textarea($how_to_apply) . '</textarea></p>';
    }

    /**
     * Save meta box content
     */
    public function save_job_meta($post_id)
    {
        if (!isset($_POST['job_meta_box_nonce']) || !wp_verify_nonce($_POST['job_meta_box_nonce'], 'job_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'job_title',
            'worldwide',
            'salary_min',
            'salary_max',
            'how_to_apply',
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks()
    {

        $plugin_public = new Wp_Remote_Jobs_Public($this->get_plugin_name(), $this->get_version());

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
     * @return    Wp_Remote_Jobs_Loader    Orchestrates the hooks of the plugin.
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
