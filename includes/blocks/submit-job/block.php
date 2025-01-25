<?php

function submit_job_block_init()
{
    register_block_type(__DIR__ . '/build', array(
        'render_callback' => 'render_submit_job_block',
    ));
}
add_action('init', 'submit_job_block_init');

function render_submit_job_block($attributes, $content)
{
    if (!is_user_logged_in()) {
        return sprintf('<p>%s</p>', esc_html__('Please log in to submit a job.', 'remote-jobs'));
    }

    // Start the session if it hasn't been started already
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    ob_start();

    // Check if we have a successful submission
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (isset($_GET['job_submitted']) && $_GET['job_submitted'] === 'success' && wp_verify_nonce($nonce, 'job_submission')) {
        ?>
        <div class="job-submission-success">
            <h2><?php esc_html_e('Job Submitted Successfully!', 'remote-jobs'); ?></h2>
            <p><?php esc_html_e('Thank you for submitting your job listing. It will be reviewed by our team and published soon.', 'remote-jobs'); ?></p>
            <p>
                <a href="<?php echo esc_url(remove_query_arg('job_submitted')); ?>" class="button submit-another-job">
                    <?php esc_html_e('Submit Another Job', 'remote-jobs'); ?>
                </a>
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    // If no successful submission, show the form
    ?>
    <form id="job-submission-form" method="post" class="job-submission-form">
        <?php wp_nonce_field('submit_job_action', 'submit_job_nonce'); ?>
        
        <div class="form-field">
            <label for="job_title"><?php esc_html_e('Job Title', 'remote-jobs'); ?></label>
            <input type="text" id="job_title" name="job_title" required />
        </div>

        <div class="form-field">
            <label for="job_description"><?php esc_html_e('Job Description', 'remote-jobs'); ?></label>
            <textarea id="job_description" name="job_description" rows="10" required></textarea>
        </div>

        <div class="form-field">
            <label for="company_name"><?php esc_html_e('Company Name', 'remote-jobs'); ?></label>
            <input type="text" id="company_name" name="company_name" required />
        </div>

        <div class="form-field">
            <label for="company_website"><?php esc_html_e('Company Website', 'remote-jobs'); ?></label>
            <input type="url" id="company_website" name="company_website" />
        </div>

        <div class="form-field">
            <input type="submit" value="<?php esc_attr_e('Submit Job', 'remote-jobs'); ?>" class="submit-job-button" />
        </div>
    </form>
    <?php
    return ob_get_clean();
}

// Enqueue necessary scripts and styles
function remjobs_enqueue_submit_job_assets() {
    if (has_block('remjobs/submit-job')) {
        wp_enqueue_style('remjobs-submit-job', plugin_dir_url(__FILE__) . 'style.css', array(), '1.0.0');
        wp_enqueue_script('remjobs-submit-job', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), '1.0.0', true);
    }
}
add_action('wp_enqueue_scripts', 'remjobs_enqueue_submit_job_assets');

function handle_job_submission()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Properly check and sanitize REQUEST_METHOD
    $request_method = '';
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $request_method = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
    }

    if ($request_method !== 'POST') {
        return;
    }

    // Verify nonce
    $nonce = isset($_POST['submit_job_nonce']) ? sanitize_text_field(wp_unslash($_POST['submit_job_nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'submit_job_action')) {
        wp_die(
            esc_html__('Security check failed. Please try again.', 'remote-jobs'),
            esc_html__('Security Error', 'remote-jobs')
        );
        return;
    }

    // Sanitize form data
    $job_title = sanitize_text_field(wp_unslash($_POST['job_title'] ?? ''));
    $job_description = wp_kses_post(wp_unslash($_POST['job_description'] ?? ''));
    $application_link = sanitize_text_field(wp_unslash($_POST['application_link'] ?? ''));
    $worldwide = sanitize_text_field(wp_unslash($_POST['worldwide'] ?? ''));
    $employment_type = sanitize_text_field(wp_unslash($_POST['employment_type'] ?? ''));

    // Create block template content
    $template_content = sprintf(
        '<!-- wp:columns -->
        <div class="wp-block-columns">
            <!-- wp:column {"width":"66.66%%"} -->
            <div class="wp-block-column" style="flex-basis:66.66%%">
                <div class="wp-block-group job-description">
                    <!-- wp:paragraph -->
                    <p>%s</p>
                    <!-- /wp:paragraph -->
                </div>
              
            </div>
            <!-- /wp:column -->

            <!-- wp:column {"width":"33.33%%"} -->
            <div class="wp-block-column" style="flex-basis:33.33%%">
                <!-- wp:wp-remote-jobs/job-side-bar /-->
            </div>
            <!-- /wp:column -->
        </div>
        <!-- /wp:columns -->',
        wp_kses_post($job_description)
    );

    // Create job post
    $job_id = wp_insert_post(array(
        'post_title'   => $job_title,
        'post_content' => $template_content,
        'post_status'  => 'pending',
        'post_type'    => 'jobs',
        'post_author'  => get_current_user_id(),
    ));

    if ($job_id) {
        // Set taxonomies and meta fields
        if (isset($_POST['job_category'])) {
            wp_set_object_terms($job_id, intval(wp_unslash($_POST['job_category'])), 'job_category');
        }

        // Handle job skills with proper sanitization
        $job_skills = array();
        if (isset($_POST['job_skills']) && is_array($_POST['job_skills'])) {
            $raw_skills = array_map('sanitize_text_field', wp_unslash($_POST['job_skills'])); // Sanitize and unslash array
            $job_skills = array_map(function ($skill) {
                // If it's a numeric ID (existing term)
                if (is_numeric($skill)) {
                    return intval($skill);
                }
                // If it's a new skill (text)
                return sanitize_text_field($skill); // No need to unslash again here
            }, $raw_skills);
        }

        if (!empty($job_skills)) {
            wp_set_object_terms($job_id, $job_skills, 'job_skills');
        }

        // Update meta fields
        update_post_meta($job_id, '_worldwide', $worldwide);
        update_post_meta($job_id, '_application_link', $application_link);
        update_post_meta($job_id, '_employment_type', $employment_type);

        // Clear session and redirect with nonce
        unset($_SESSION['job_form_data']);
        $redirect_url = add_query_arg(
            array(
                'job_submitted' => 'success',
                '_wpnonce' => wp_create_nonce('job_submission')
            ),
            wp_get_referer()
        );
        wp_redirect($redirect_url);
        exit;
    }
}
add_action('template_redirect', 'handle_job_submission');

// Add a function to clear session data when the job is successfully submitted
function clear_job_session_data()
{
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (isset($_GET['job_submitted']) &&
        $_GET['job_submitted'] === 'success' &&
        wp_verify_nonce($nonce, 'job_submission')) {

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['job_form_data']);
    }
}
add_action('template_redirect', 'clear_job_session_data');

?>