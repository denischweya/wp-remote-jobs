<?php
/**
 * Submit Job Block
 *
 * @package RemJobs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Render the submit job block.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block content.
 * @param WP_Block $block      Block instance.
 * @return string  The block HTML.
 */
function remjobs_render_submit_job_block($attributes, $content, $block)
{
    ob_start();

    // Check if user is logged in
    if (!is_user_logged_in()) {
        return '<div class="remjobs-notice remjobs-error">' .
            esc_html__('You must be logged in to submit a job.', 'remote-jobs') .
            '</div>';
    }

    // Check if user has permission to submit jobs
    if (!current_user_can('publish_posts')) {
        return '<div class="remjobs-notice remjobs-error">' .
            esc_html__('You do not have permission to submit jobs.', 'remote-jobs') .
            '</div>';
    }

    // Process success message if present - only check when the parameter exists
    if (isset($_GET['remjobs_job_submitted'])) {
        // Sanitize the job_submitted value
        $job_submitted = sanitize_text_field(wp_unslash($_GET['remjobs_job_submitted']));

        // Only proceed if we have both the success value and a nonce
        if ($job_submitted === 'success') {
            // Verify the nonce - both must be present and valid
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

            if (!empty($nonce) && wp_verify_nonce($nonce, 'remjobs_job_submission')) {
                echo '<div class="remjobs-notice remjobs-success">';
                echo '<p>' . esc_html__('Job submitted successfully! It will be reviewed shortly.', 'remote-jobs') . '</p>';
                echo '</div>';
            }
            // If nonce is missing or invalid, silently ignore (don't show success message)
        }
    }

    // Generate a unique nonce for this form
    $nonce = wp_create_nonce('remjobs_job_submission_action');
    ?>
<div <?php echo wp_kses_post(get_block_wrapper_attributes(['class' => 'submit-job-form'])); ?>>
    <form id="submit-job-form" class="job-submission-form" method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="remjobs_submit_job">
        <input type="hidden" name="remjobs_job_submission_nonce"
            value="<?php echo esc_attr($nonce); ?>">

        <div class="form-group">
            <label
                for="job-title"><?php esc_html_e('Job Title', 'remote-jobs'); ?></label>
            <span
                class="field-description"><?php esc_html_e('Enter a descriptive title for the job position', 'remote-jobs'); ?></span>
            <input type="text" id="job-title" name="job_title" required />
        </div>

        <div class="form-group">
            <label
                for="job-description"><?php esc_html_e('Job Description', 'remote-jobs'); ?></label>
            <span
                class="field-description"><?php esc_html_e('Describe the job responsibilities, requirements, and benefits', 'remote-jobs'); ?></span>
            <textarea id="job-description" name="job_description" required></textarea>
        </div>

        <div class="form-group">
            <label
                for="job-type"><?php esc_html_e('Employment Type', 'remote-jobs'); ?></label>
            <select id="job-type" name="job_type" required>
                <option value="">
                    <?php esc_html_e('Select Type', 'remote-jobs'); ?>
                </option>
                <option value="full-time">
                    <?php esc_html_e('Full Time', 'remote-jobs'); ?>
                </option>
                <option value="part-time">
                    <?php esc_html_e('Part Time', 'remote-jobs'); ?>
                </option>
                <option value="contract">
                    <?php esc_html_e('Contract', 'remote-jobs'); ?>
                </option>
            </select>
        </div>

        <div class="form-group">
            <label
                for="salary-range"><?php esc_html_e('Salary Range', 'remote-jobs'); ?></label>
            <select id="salary-range" name="salary_range" required>
                <option value="">
                    <?php esc_html_e('Select Range', 'remote-jobs'); ?>
                </option>
                <option value="30k-50k">
                    <?php esc_html_e('$30,000 - $50,000', 'remote-jobs'); ?>
                </option>
                <option value="50k-80k">
                    <?php esc_html_e('$50,000 - $80,000', 'remote-jobs'); ?>
                </option>
                <option value="80k-120k">
                    <?php esc_html_e('$80,000 - $120,000', 'remote-jobs'); ?>
                </option>
                <option value="120k+">
                    <?php esc_html_e('$120,000+', 'remote-jobs'); ?>
                </option>
            </select>
        </div>

        <div class="form-group">
            <label
                for="application-link"><?php esc_html_e('Application Link', 'remote-jobs'); ?></label>
            <span
                class="field-description"><?php esc_html_e('Full URL where candidates can apply (including https://)', 'remote-jobs'); ?></span>
            <input type="url" id="application-link" name="application_link" required />
        </div>

        <button type="submit"
            class="submit-button"><?php esc_html_e('Submit Job', 'remote-jobs'); ?></button>
    </form>
</div>
<?php
    return ob_get_clean();
}

/**
 * Handle job submission form processing.
 * This function runs only when the form is submitted.
 */
function remjobs_handle_job_submission()
{
    // Verify request method
    if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    // Verify nonce with proper sanitization
    if (!isset($_POST['remjobs_job_submission_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['remjobs_job_submission_nonce'])),
            'remjobs_job_submission_action'
        )) {
        wp_die(
            esc_html__('Security check failed. Please try again.', 'remote-jobs'),
            esc_html__('Security Error', 'remote-jobs'),
            array('response' => 403)
        );
    }

    // Verify user is logged in
    if (!is_user_logged_in()) {
        wp_die(
            esc_html__('You must be logged in to submit a job.', 'remote-jobs'),
            esc_html__('Authentication Error', 'remote-jobs'),
            array('response' => 401)
        );
    }

    // Check if user has permission to submit jobs
    if (!current_user_can('publish_posts')) {
        wp_die(
            esc_html__('You do not have permission to submit jobs.', 'remote-jobs'),
            esc_html__('Authorization Error', 'remote-jobs'),
            array('response' => 403)
        );
    }

    // Sanitize and validate form data
    $job_title = isset($_POST['job_title']) ? sanitize_text_field(wp_unslash($_POST['job_title'])) : '';
    $job_description = isset($_POST['job_description']) ? wp_kses_post(wp_unslash($_POST['job_description'])) : '';
    $job_type = isset($_POST['job_type']) ? sanitize_text_field(wp_unslash($_POST['job_type'])) : '';
    $salary_range = isset($_POST['salary_range']) ? sanitize_text_field(wp_unslash($_POST['salary_range'])) : '';
    $application_link = isset($_POST['application_link']) ? esc_url_raw(wp_unslash($_POST['application_link'])) : '';

    // Validate required fields
    $errors = array();

    if (empty($job_title)) {
        $errors[] = esc_html__('Job title is required.', 'remote-jobs');
    }

    if (empty($job_description)) {
        $errors[] = esc_html__('Job description is required.', 'remote-jobs');
    }

    if (empty($job_type)) {
        $errors[] = esc_html__('Employment type is required.', 'remote-jobs');
    } elseif (!in_array($job_type, array('full-time', 'part-time', 'contract'), true)) {
        $errors[] = esc_html__('Invalid employment type.', 'remote-jobs');
    }

    if (empty($salary_range)) {
        $errors[] = esc_html__('Salary range is required.', 'remote-jobs');
    } elseif (!in_array($salary_range, array('30k-50k', '50k-80k', '80k-120k', '120k+'), true)) {
        $errors[] = esc_html__('Invalid salary range.', 'remote-jobs');
    }

    if (empty($application_link)) {
        $errors[] = esc_html__('Application link is required.', 'remote-jobs');
    } elseif (!filter_var($application_link, FILTER_VALIDATE_URL)) {
        $errors[] = esc_html__('Please enter a valid URL for the application link.', 'remote-jobs');
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        // Store errors in a transient
        set_transient('remjobs_job_submission_errors_' . get_current_user_id(), $errors, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }

    // Create job post
    $job_data = array(
        'post_title'    => $job_title,
        'post_content'  => $job_description,
        'post_status'   => 'pending', // Jobs require moderation
        'post_type'     => 'remjobs_jobs',  // Use consistent post type name
        'post_author'   => get_current_user_id(),
    );

    $job_id = wp_insert_post($job_data);

    if (!is_wp_error($job_id)) {
        // Set job category if provided
        if (isset($_POST['job_category'])) {
            $category_id = absint(wp_unslash($_POST['job_category'])); // Sanitize as integer
            if ($category_id > 0) {
                wp_set_object_terms($job_id, $category_id, 'remjobs_job_category');
            }
        }

        // Set job skills if provided with proper sanitization
        if (isset($_POST['job_skills']) && is_array($_POST['job_skills'])) {
            $raw_skills = array_map('sanitize_text_field', wp_unslash($_POST['job_skills']));
            $job_skills = array();

            foreach ($raw_skills as $skill) {
                // Handle existing terms
                if (is_numeric($skill)) {
                    $job_skills[] = absint($skill);
                    continue;
                }

                // Check if term exists first
                $existing_term = term_exists($skill, 'remjobs_job_skills');
                if ($existing_term) {
                    $job_skills[] = $existing_term['term_id'];
                } else {
                    // Create new term if it doesn't exist
                    $new_term = wp_insert_term($skill, 'remjobs_job_skills');
                    if (!is_wp_error($new_term)) {
                        $job_skills[] = $new_term['term_id'];
                    }
                }
            }

            if (!empty($job_skills)) {
                wp_set_object_terms($job_id, $job_skills, 'remjobs_job_skills');
            }
        }

        // Save additional meta data
        update_post_meta($job_id, '_remjobs_job_type', $job_type);
        update_post_meta($job_id, '_remjobs_salary_range', $salary_range);
        update_post_meta($job_id, '_remjobs_application_link', $application_link);

        // Clean up session data if used
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['remjobs_job_form_data'])) {
            unset($_SESSION['remjobs_job_form_data']);
        }

        // Redirect with success message
        wp_safe_redirect(add_query_arg(array(
            'remjobs_job_submitted' => 'success',
            '_wpnonce' => wp_create_nonce('remjobs_job_submission')
        ), wp_get_referer() ?: home_url()));
        exit;
    } else {
        // If job creation failed, redirect with error
        set_transient(
            'remjobs_job_submission_errors_' . get_current_user_id(),
            array(esc_html__('Error creating job listing. Please try again.', 'remote-jobs')),
            5 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }
}
add_action('admin_post_remjobs_submit_job', 'remjobs_handle_job_submission');
add_action('admin_post_nopriv_remjobs_submit_job', 'remjobs_handle_job_submission');

/**
 * Display job submission messages (errors or success).
 *
 * @return bool True if messages were displayed, false otherwise.
 */
function remjobs_display_job_submission_message()
{
    $output = '';
    $user_id = get_current_user_id();

    // Check for errors
    $errors = get_transient('remjobs_job_submission_errors_' . $user_id);
    if ($errors) {
        delete_transient('remjobs_job_submission_errors_' . $user_id);

        $output .= '<div class="remjobs-notice remjobs-error">';
        foreach ($errors as $error) {
            $output .= '<p>' . $error . '</p>';
        }
        $output .= '</div>';
    }

    // Check for success message - only when the parameter exists
    if (isset($_GET['remjobs_job_submitted'])) {
        $job_submitted = sanitize_text_field(wp_unslash($_GET['remjobs_job_submitted']));

        // Only proceed if we have the success value
        if ($job_submitted === 'success') {
            // Verify the nonce - both must be present and valid
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

            if (!empty($nonce) && wp_verify_nonce($nonce, 'remjobs_job_submission')) {
                // Clean up any session data if used
                if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['remjobs_job_form_data'])) {
                    unset($_SESSION['remjobs_job_form_data']);
                }

                $output .= '<div class="remjobs-notice remjobs-success">';
                $output .= '<p>' . esc_html__('Job submitted successfully! It will be reviewed shortly.', 'remote-jobs') . '</p>';
                $output .= '<a href="' . esc_url(remove_query_arg(array('remjobs_job_submitted', '_wpnonce'))) . '" class="button submit-another-job">';
                $output .= esc_html__('Submit Another Job', 'remote-jobs');
                $output .= '</a>';
                $output .= '</div>';
            }
            // If nonce is missing or invalid, silently ignore (don't show success message)
        }
    }

    if (!empty($output)) {
        echo wp_kses_post($output);
        return true;
    }

    return false;
}
?>