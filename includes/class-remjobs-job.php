<?php

/**
 * Handles job-related functionality and custom fields
 *
 * @link       https://denis.swishfolio.com/
 * @since      1.0.0
 * @package    Remote_Jobs
 * @subpackage Remote_Jobs/includes
 */

declare(strict_types=1);

class Remjobs_Job
{
    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     */
    public function __construct()
    {
        add_action('init', array($this, 'register_meta_fields'));
        add_action('add_meta_boxes', array($this, 'add_job_meta_boxes'));
        add_action('save_post_remjobs', array($this, 'save_job_meta'));
    }

    /**
     * Register custom meta fields for jobs
     *
     * @since    1.0.0
     */
    public function register_meta_fields(): void
    {
        register_post_meta('remjobs', 'salary_range', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_post_meta('remjobs', 'application_deadline', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_post_meta('remjobs', 'company_name', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_post_meta('remjobs', 'company_website', array(
            'show_in_rest' => true,
            'single' => true,
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw'
        ));
    }

    /**
     * Add meta boxes for job details
     *
     * @since    1.0.0
     */
    public function add_job_meta_boxes(): void
    {
        add_meta_box(
            'remjobs_details',
            __('Job Details', 'remote-jobs'),
            array($this, 'render_job_meta_box'),
            'remjobs',
            'normal',
            'high'
        );
    }

    /**
     * Render the job meta box
     *
     * @since    1.0.0
     * @param    WP_Post    $post    The post object
     */
    public function render_job_meta_box($post): void
    {
        $salary_range = get_post_meta($post->ID, 'salary_range', true);
        $application_deadline = get_post_meta($post->ID, 'application_deadline', true);
        $company_name = get_post_meta($post->ID, 'company_name', true);
        $company_website = get_post_meta($post->ID, 'company_website', true);

        wp_nonce_field('remjobs_save_meta', 'remjobs_meta_nonce');
        ?>
<p>
	<label
		for="salary_range"><?php esc_html_e('Salary Range', 'remote-jobs'); ?></label><br>
	<input type="text" id="salary_range" name="salary_range"
		value="<?php echo esc_attr($salary_range); ?>"
		class="widefat">
</p>
<p>
	<label
		for="application_deadline"><?php esc_html_e('Application Deadline', 'remote-jobs'); ?></label><br>
	<input type="date" id="application_deadline" name="application_deadline"
		value="<?php echo esc_attr($application_deadline); ?>"
		class="widefat">
</p>
<p>
	<label
		for="company_name"><?php esc_html_e('Company Name', 'remote-jobs'); ?></label><br>
	<input type="text" id="company_name" name="company_name"
		value="<?php echo esc_attr($company_name); ?>"
		class="widefat">
</p>
<p>
	<label
		for="company_website"><?php esc_html_e('Company Website', 'remote-jobs'); ?></label><br>
	<input type="url" id="company_website" name="company_website"
		value="<?php echo esc_url($company_website); ?>"
		class="widefat">
</p>
<?php
    }

    /**
     * Save job meta data
     *
     * @since    1.0.0
     * @param    int    $post_id    The post ID
     */
    public function save_job_meta($post_id): void
    {
        if (!isset($_POST['remjobs_meta_nonce']) || !wp_verify_nonce($_POST['remjobs_meta_nonce'], 'remjobs_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'salary_range' => 'sanitize_text_field',
            'application_deadline' => 'sanitize_text_field',
            'company_name' => 'sanitize_text_field',
            'company_website' => 'esc_url_raw'
        );

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                $value = call_user_func($sanitize_callback, $_POST[$field]);
                update_post_meta($post_id, $field, $value);
            }
        }
    }
}
