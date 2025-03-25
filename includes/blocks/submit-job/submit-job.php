<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function remjobs_render_submit_job_block($attributes, $content, $block)
{
    ob_start();
    ?>
<div <?php echo wp_kses_post(get_block_wrapper_attributes(['class' => 'submit-job-form'])); ?>>
    <form id="submit-job-form" class="job-submission-form" method="post">
        <div class="form-group">
            <label for="job-title">Job Title</label>
            <input type="text" id="job-title" name="job_title" required />
        </div>

        <div class="form-group">
            <label for="job-description">Job Description</label>
            <textarea id="job-description" name="job_description" required></textarea>
        </div>

        <div class="form-group">
            <label for="job-type">Employment Type</label>
            <select id="job-type" name="job_type" required>
                <option value="">Select Type</option>
                <option value="full-time">Full Time</option>
                <option value="part-time">Part Time</option>
                <option value="contract">Contract</option>
            </select>
        </div>

        <div class="form-group">
            <label for="salary-range">Salary Range</label>
            <select id="salary-range" name="salary_range" required>
                <option value="">Select Range</option>
                <option value="30k-50k">$30,000 - $50,000</option>
                <option value="50k-80k">$50,000 - $80,000</option>
                <option value="80k-120k">$80,000 - $120,000</option>
                <option value="120k+">$120,000+</option>
            </select>
        </div>

        <div class="form-group">
            <label for="application-link">Application Link</label>
            <input type="url" id="application-link" name="application_link" required />
        </div>

        <button type="submit" class="submit-button">Submit Job</button>
    </form>
</div>
<?php
    return ob_get_clean();
}

// Handle form submission
function remjobs_handle_job_submission()
{
    if (!isset($_POST['remjobs_job_submission_nonce']) ||
        !wp_verify_nonce($_POST['remjobs_job_submission_nonce'], 'remjobs_job_submission_action')) {
        wp_die(esc_html__('Security check failed', 'remote-jobs'));
    }

    $job_title = sanitize_text_field(wp_unslash($_POST['job_title'] ?? ''));
    $job_description = wp_kses_post(wp_unslash($_POST['job_description'] ?? ''));

    // Create job post
    $job_data = array(
        'post_title'    => $job_title,
        'post_content'  => $job_description,
        'post_status'   => 'pending',
        'post_type'     => 'jobs'
    );

    $job_id = wp_insert_post($job_data);

    if (!is_wp_error($job_id)) {
        // Set job category
        if (isset($_POST['job_category'])) {
            wp_set_object_terms($job_id, intval(wp_unslash($_POST['job_category'])), 'remjobs_job_category');
        }

        // Set job skills
        if (isset($_POST['job_skills']) && is_array($_POST['job_skills'])) {
            $raw_skills = array_map('sanitize_text_field', wp_unslash($_POST['job_skills']));
            $job_skills = array();
            foreach ($raw_skills as $skill) {
                if (term_exists($skill, 'remjobs_job_skills')) {
                    $job_skills[] = $skill;
                } else {
                    $term = wp_insert_term($skill, 'remjobs_job_skills');
                    if (!is_wp_error($term)) {
                        $job_skills[] = $skill;
                    }
                }
            }
            wp_set_object_terms($job_id, $job_skills, 'remjobs_job_skills');
        }

        // Clear form data from session
        if (isset($_SESSION['remjobs_job_form_data'])) {
            unset($_SESSION['remjobs_job_form_data']);
        }

        // Redirect with success message
        wp_safe_redirect(add_query_arg(array(
            'remjobs_job_submitted' => 'success',
            '_wpnonce' => wp_create_nonce('remjobs_job_submission')
        ), wp_get_referer()));
        exit;
    }
}
add_action('admin_post_submit_job', 'remjobs_handle_job_submission');
add_action('admin_post_nopriv_submit_job', 'remjobs_handle_job_submission');

// Display success message
function remjobs_display_job_submission_message()
{
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

    if (isset($_GET['remjobs_job_submitted']) &&
        $_GET['remjobs_job_submitted'] === 'success' &&
        wp_verify_nonce($nonce, 'remjobs_job_submission')) {

        if (isset($_SESSION['remjobs_job_form_data'])) {
            unset($_SESSION['remjobs_job_form_data']);
        }

        echo '<div class="job-submission-success">';
        echo '<p>' . esc_html__('Job submitted successfully! It will be reviewed shortly.', 'remote-jobs') . '</p>';
        echo '<a href="' . esc_url(remove_query_arg('remjobs_job_submitted')) . '" class="button submit-another-job">';
        echo esc_html__('Submit Another Job', 'remote-jobs');
        echo '</a>';
        echo '</div>';

        return true;
    }

    return false;
}
?>