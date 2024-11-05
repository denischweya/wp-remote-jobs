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

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/submit-job/block.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/job-list/block.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/registration/registration.php';

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/blocks/job-sidebar/job-sidebar.php';

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

        // Add hook for registering blocks category
        $this->loader->add_filter('block_categories_all', $this, 'register_block_category', 10, 2);

        // Enqueue Select2 scripts and styles for the 'Submit Job' block
        $this->loader->add_action('wp_enqueue_scripts', $this, 'enqueue_select2');

        // populate job location taxonomy with countries
        // $this->loader->add_action('init', $this, 'populate_job_location_taxonomy');

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
            'supports'           => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields' ),
            'show_in_rest'       => true,
            'taxonomies'         => array('job_category', 'job_skills', 'job_location', 'employment_type', 'job_benefits', 'salary_range', 'job_tags'),
        );

        register_post_type('jobs', $args);

        // Add this line to ensure taxonomy meta boxes show up in the block editor
        add_filter('register_post_type_args', array($this, 'add_taxonomies_to_job_cpt'), 10, 2);
    }

    /**
     * Ensure taxonomy meta boxes show up in the block editor
     */
    public function add_taxonomies_to_job_cpt($args, $post_type)
    {
        if ('jobs' === $post_type) {
            $args['taxonomies'] = array('job_category', 'job_skills', 'job_location', 'employment_type', 'job_benefits', 'salary_range', 'job_tags');
        }
        return $args;
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
            'show_in_rest' => true,
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
            'show_in_rest' => true,
        ));

        // Register Location taxonomy
        register_taxonomy('job_location', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Locations', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Location', 'taxonomy singular name', 'wp-remote-jobs'),
                'search_items' => __('Search Location', 'wp-remote-jobs'),
                'all_items' => __('All Location', 'wp-remote-jobs'),
                'parent_item' => __('Parent Location', 'wp-remote-jobs'),
                'parent_item_colon' => __('Parent Location:', 'wp-remote-jobs'),
                'edit_item' => __('Edit Location', 'wp-remote-jobs'),
                'update_item' => __('Update Location', 'wp-remote-jobs'),
                'add_new_item' => __('Add New Location', 'wp-remote-jobs'),
                'new_item_name' => __('New Location Name', 'wp-remote-jobs'),
                'menu_name' => __('Location', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-location'),
            'show_in_rest' => true,
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
            'show_in_rest' => true,
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
            'show_in_rest' => true,
        ));

        // Register Salary Range taxonomy
        register_taxonomy('salary_range', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Salary Ranges', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Salary Range', 'taxonomy singular name', 'wp-remote-jobs'),
                'search_items' => __('Search Salary Ranges', 'wp-remote-jobs'),
                'all_items' => __('All Salary Ranges', 'wp-remote-jobs'),
                'parent_item' => __('Parent Salary Range', 'wp-remote-jobs'),
                'parent_item_colon' => __('Parent Salary Range:', 'wp-remote-jobs'),
                'edit_item' => __('Edit Salary Range', 'wp-remote-jobs'),
                'update_item' => __('Update Salary Range', 'wp-remote-jobs'),
                'add_new_item' => __('Add New Salary Range', 'wp-remote-jobs'),
                'new_item_name' => __('New Salary Range Name', 'wp-remote-jobs'),
                'menu_name' => __('Salary Range', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'salary-range'),
            'show_in_rest' => true,
        ));

        // Add Job Tags taxonomy
        register_taxonomy('job_tags', 'jobs', array(
            'hierarchical' => true,
            'labels' => array(
                'name' => _x('Job Tags', 'taxonomy general name', 'wp-remote-jobs'),
                'singular_name' => _x('Job Tag', 'taxonomy singular name', 'wp-remote-jobs'),
                'search_items' => __('Search Job Tags', 'wp-remote-jobs'),
                'all_items' => __('All Job Tags', 'wp-remote-jobs'),
                'parent_item' => __('Parent Job Tag', 'wp-remote-jobs'),
                'parent_item_colon' => __('Parent Job Tag:', 'wp-remote-jobs'),
                'edit_item' => __('Edit Job Tag', 'wp-remote-jobs'),
                'update_item' => __('Update Job Tag', 'wp-remote-jobs'),
                'add_new_item' => __('Add New Job Tag', 'wp-remote-jobs'),
                'new_item_name' => __('New Job Tag Name', 'wp-remote-jobs'),
                'menu_name' => __('Job Tags', 'wp-remote-jobs'),
            ),
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'job-tags'),
            'show_in_rest' => true,
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

        $worldwide = get_post_meta($post->ID, '_worldwide', true);
        $job_location = get_post_meta($post->ID, '_job_location', true);
        $application_link = get_post_meta($post->ID, '_application_link', true);

        echo '<p><label>' . __('Is position open worldwide?', 'wp-remote-jobs') . '</label><br>';
        echo '<input type="radio" id="worldwide_yes" name="worldwide" value="yes" ' . checked($worldwide, 'yes', false) . '>';
        echo '<label for="worldwide_yes">Yes</label>';
        echo '<input type="radio" id="worldwide_no" name="worldwide" value="no" ' . checked($worldwide, 'no', false) . '>';
        echo '<label for="worldwide_no">No</label></p>';

        echo '<p><label for="job_location">' . __('Job Location', 'wp-remote-jobs') . '</label><br>';
        echo '<select id="job_location" name="job_location" class="select2-country">';
        echo '<option value="">' . __('Select a country', 'wp-remote-jobs') . '</option>';
        $countries = $this->get_countries_list();
        foreach ($countries as $code => $name) {
            echo '<option value="' . esc_attr($code) . '" ' . selected($job_location, $code, false) . '>' . esc_html($name) . '</option>';
        }
        echo '</select></p>';

        // Add Application Link field
        echo '<p><label for="application_link">' . __('Application Link', 'wp-remote-jobs') . '</label><br>';
        echo '<input type="url" id="application_link" name="application_link" value="' . esc_attr($application_link) . '" style="width: 100%;" placeholder="https://example.com/apply">';
        echo '</p>';

        // Enqueue Select2 scripts and styles
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js', array('jquery'), '4.0.13', true);
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css', array(), '4.0.13');

        // Initialize Select2
        wp_add_inline_script('select2', '
            jQuery(document).ready(function($) {
                $(".select2-country").select2();
            });
        ');
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
            'worldwide',
            'job_location',
            'application_link',
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Get list of countries
     */
    private function get_countries_list()
    {
        return array(
            'US' => 'United States',
            'CA' => 'Canada',
            'GB' => 'United Kingdom',
            'FR' => 'France',
            'DE' => 'Germany',
            // Add more countries as needed
        );
    }


    // Function to check if the block is present on the page
    public function is_submit_job_block_present()
    {
        if (has_block('create-block/submit-job')) {
            return true;
        }

        // Check for the block in post content
        global $post;
        if (is_a($post, 'WP_Post') && has_blocks($post->post_content)) {
            $blocks = parse_blocks($post->post_content);
            foreach ($blocks as $block) {
                if ('create-block/submit-job' === $block['blockName']) {
                    return true;
                }
            }
        }

        return false;
    }
    public function populate_job_location_taxonomy()
    {
        // Check if the taxonomy exists
        if (!taxonomy_exists('job_location')) {
            error_log('Job location taxonomy does not exist.');
            return;
        }

        // Check if the function has already run
        if (get_option('job_location_populated')) {
            return;
        }

        // List of countries
        $countries = array(
            'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Australia', 'Austria',
            'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan',
            'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cabo Verde', 'Cambodia',
            'Cameroon', 'Canada', 'Central African Republic', 'Chad', 'Chile', 'China', 'Colombia', 'Comoros', 'Congo', 'Costa Rica',
            'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Democratic Republic of the Congo', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador',
            'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland', 'France',
            'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Grenada', 'Guatemala', 'Guinea', 'Guinea-Bissau',
            'Guyana', 'Haiti', 'Honduras', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland',
            'Israel', 'Italy', 'Jamaica', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya', 'Kiribati', 'Kuwait', 'Kyrgyzstan',
            'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar',
            'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Mauritania', 'Mauritius', 'Mexico', 'Micronesia',
            'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia', 'Nauru', 'Nepal',
            'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Korea', 'North Macedonia', 'Norway', 'Oman', 'Pakistan',
            'Palau', 'Palestine', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar',
            'Romania', 'Russia', 'Rwanda', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia',
            'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'South Africa',
            'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan',
            'Tanzania', 'Thailand', 'Timor-Leste', 'Togo', 'Tonga', 'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Tuvalu',
            'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela',
            'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe'
        );

        // Add each country as a term in the taxonomy
        foreach ($countries as $country) {
            if (!term_exists($country, 'job_location')) {
                $result = wp_insert_term($country, 'job_location');
                if (is_wp_error($result)) {
                    error_log('Failed to insert country term: ' . $country . '. Error: ' . $result->get_error_message());
                } else {
                    error_log('Successfully inserted country term: ' . $country);
                }
            } else {
                error_log('Country term already exists: ' . $country);
            }
        }

        // Set option to indicate that the function has run
        update_option('job_location_populated', true);
    }


    // Function to enqueue Select2 files
    public function enqueue_select2()
    {
        //if ($this->is_submit_job_block_present()) {
        wp_enqueue_style('select2-css', plugin_dir_url(__FILE__) . '../public/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script('select2-js', plugin_dir_url(__FILE__) . '../public/js/select2.min.js', array( 'jquery' ), '4.1.0-rc.0', true);
        // }
        wp_enqueue_script('job-filter', plugin_dir_url(__FILE__) . '../includes/blocks/list-jobs/job-list/src/view.js', array('jquery'), '1.0', true);

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

    /**
     * Register custom block category for Remote Jobs
     *
     * @param array  $categories Array of block categories.
     * @param object $post Post being loaded.
     * @return array Modified array of block categories.
     */
    public function register_block_category($categories, $post)
    {
        $remote_jobs_category = array(
            array(
                'slug' => 'remote-jobs',
                'title' => __('Remote Jobs', 'my-block'),
                'icon' => '<svg enable-background="new 0 0 130 130" height="20" width="20" viewBox="0 0 130 130" xmlns="http://www.w3.org/2000/svg"><line fill="none" stroke="#3B64F3" stroke-miterlimit="10" stroke-width="2" x1="29.4" x2="52.588" y1="82.311" y2="82.311"/><line fill="none" stroke="#3B64F3" stroke-miterlimit="10" stroke-width="2" x1="29.4" x2="52.588" y1="63.641" y2="63.641"/><line fill="none" stroke="#3B64F3" stroke-miterlimit="10" stroke-width="2" x1="29.4" x2="52.588" y1="43.756" y2="43.756"/><path d="M92.926,56.384v51.298c0,2.569-2.081,4.66-4.639,4.66H23.564c-2.558,0-4.639-2.091-4.639-4.66v-86.18  c0-2.57,2.081-4.66,4.639-4.66h64.723c2.558,0,4.639,2.09,4.639,4.66v17.131l2-2.002V21.502c0-3.678-2.973-6.66-6.639-6.66H23.564  c-3.666,0-6.639,2.982-6.639,6.66v86.18c0,3.678,2.973,6.66,6.639,6.66h64.723c3.666,0,6.639-2.982,6.639-6.66v-53.3L92.926,56.384z  " fill="#3F65F0"/><path d="M120.463,25.534c-0.557-0.898-1.405-1.936-2.393-2.923c-1.194-1.195-3.462-3.199-5.224-3.199  c-0.649,0-1.247,0.242-1.681,0.677l-7.667,7.666L66.504,64.834c-0.802,0.802-1.354,1.759-1.662,2.775L56.718,83.41l16.313-7.45  c1.085-0.305,2.081-0.879,2.9-1.698l37.688-37.771l6.975-6.975C121.251,28.858,121.762,27.634,120.463,25.534z M64.922,71.716  c0.318,0.93,0.841,1.805,1.582,2.546c0.652,0.653,1.419,1.15,2.252,1.48l-8.193,4.07L64.922,71.716z M74.518,72.848  c-0.882,0.881-2.054,1.366-3.301,1.366c-1.246,0-2.418-0.485-3.299-1.366c-0.879-0.88-1.363-2.052-1.363-3.3  c0-1.249,0.484-2.421,1.363-3.3l34.402-34.4c0.484,1.073,1.402,2.305,2.805,3.709c0.894,0.894,2.387,2.239,3.82,2.861L74.518,72.848  z M119.18,28.102l-8.564,8.564c-0.075,0.075-0.186,0.09-0.266,0.09c-0.414,0-1.743-0.546-3.811-2.614  c-2.402-2.405-2.823-3.776-2.523-4.076l8.564-8.563c0.079-0.08,0.201-0.091,0.267-0.091c0.414,0,1.743,0.546,3.81,2.613  c0.867,0.867,1.635,1.8,2.106,2.562C118.838,26.708,119.491,27.79,119.18,28.102z" fill="#3F65F0"/></svg>',
            )
        );

        // Add the Remote Jobs category to the beginning of the list
        array_unshift($categories, $remote_jobs_category[0]);

        return $categories;
    }

}
