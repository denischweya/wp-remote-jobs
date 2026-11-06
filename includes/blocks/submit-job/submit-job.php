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

    // Process success message if present - verify nonce first before accessing any parameters
    if (isset($_GET['remjobs_job_submitted']) && isset($_GET['_wpnonce'])) {
        // Verify the nonce FIRST before accessing any GET parameters
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        if (wp_verify_nonce($nonce, 'remjobs_job_submission')) {
            // Only now access the job_submitted parameter after nonce verification
            $job_submitted = sanitize_text_field(wp_unslash($_GET['remjobs_job_submitted']));

            if ($job_submitted === 'success') {
                echo '<div class="remjobs-notice remjobs-success">';
                echo '<p>' . esc_html__('Job submitted successfully! It will be reviewed shortly.', 'remote-jobs') . '</p>';
                echo '</div>';
            }
        }
        // If nonce verification fails, silently ignore (don't show success message)
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
            <label for="company-name"><?php esc_html_e('Company Name', 'remote-jobs'); ?></label>
            <input type="text" id="company-name" name="company_name" required />
        </div>

        <div class="form-group">
            <label for="job-location"><?php esc_html_e('Remote Location', 'remote-jobs'); ?></label>
            <input type="text" id="job-location" name="job_location" placeholder="Worldwide, EMEA, US…" required />
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

        <?php
        $gateway_choices = array();
        $payments = function_exists('remjobs_payments') ? remjobs_payments() : null;
        if ($payments) {
            $gateway_choices = $payments->ui_gateway_choices();
        }
        ?>
        <div class="form-group">
            <label for="remjobs-plan"><?php esc_html_e('Listing type', 'remote-jobs'); ?></label>
            <select id="remjobs-plan" name="plan">
                <option value="free"><?php esc_html_e('Free — reviewed before publishing', 'remote-jobs'); ?></option>
                <?php if (!empty($gateway_choices) && class_exists('Remjobs_Plans')) :
                    $single = Remjobs_Plans::get('single');
                    $monthly = Remjobs_Plans::get('monthly');
                    if ($single) : ?>
                        <option value="single">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: price, 2: currency code */
                                __('Featured — one-time (%1$s %2$s)', 'remote-jobs'),
                                $single['price'],
                                $single['currency']
                            ));
                            ?>
                        </option>
                    <?php endif;
                    if ($monthly) : ?>
                        <option value="monthly">
                            <?php
                            echo esc_html(sprintf(
                                /* translators: 1: price, 2: currency code */
                                __('Featured — monthly (%1$s %2$s/mo)', 'remote-jobs'),
                                $monthly['price'],
                                $monthly['currency']
                            ));
                            ?>
                        </option>
                    <?php endif;
                endif; ?>
            </select>
        </div>

        <?php if (!empty($gateway_choices)) : ?>
        <div class="form-group" id="remjobs-gateway-group" style="display:none;">
            <label for="remjobs-gateway"><?php esc_html_e('Payment method', 'remote-jobs'); ?></label>
            <select id="remjobs-gateway" name="gateway">
                <?php foreach ($gateway_choices as $gid => $glabel) : ?>
                    <option value="<?php echo esc_attr($gid); ?>"><?php echo esc_html($glabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <script>
        (function () {
            var planSelect = document.getElementById('remjobs-plan');
            var gatewayGroup = document.getElementById('remjobs-gateway-group');
            if (!planSelect || !gatewayGroup) { return; }
            function toggle() {
                gatewayGroup.style.display = (planSelect.value === 'free') ? 'none' : '';
            }
            planSelect.addEventListener('change', toggle);
            toggle();
        })();
        </script>
        <?php endif; ?>

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
    $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
    $job_location = isset($_POST['job_location']) ? sanitize_text_field(wp_unslash($_POST['job_location'])) : '';
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

    if (empty($company_name) || empty($job_location)) {
        $errors[] = esc_html__('Company name and remote location are required.', 'remote-jobs');
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
        update_post_meta($job_id, '_remjobs_salary', $salary_range);
        update_post_meta($job_id, '_remjobs_application_link', $application_link);
        update_post_meta($job_id, '_remjobs_application_url', $application_link);
        update_post_meta($job_id, '_remjobs_company_name', $company_name);
        update_post_meta($job_id, '_remjobs_company', $company_name);
        update_post_meta($job_id, '_remjobs_location', $job_location);
        wp_set_object_terms($job_id, $job_location, 'remjobs_job_location');
        wp_set_object_terms($job_id, $job_type, 'remjobs_job_employment_type');

        // Clean up session data if used
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['remjobs_job_form_data'])) {
            unset($_SESSION['remjobs_job_form_data']);
        }

        // Paid plan: hold the job as a draft awaiting payment and route to
        // checkout. Free (or no) plan: leave the existing pending/moderation
        // flow untouched.
        $plan = isset($_POST['plan']) ? sanitize_text_field(wp_unslash($_POST['plan'])) : '';
        $gateway = isset($_POST['gateway']) ? sanitize_text_field(wp_unslash($_POST['gateway'])) : '';

        try {
            $checkout_url = remjobs_route_submitted_job($job_id, $plan, $gateway, get_current_user_id());
        } catch (Exception $e) {
            set_transient('remjobs_ui_error_' . get_current_user_id(), $e->getMessage(), 60);
            wp_safe_redirect(wp_get_referer() ?: home_url());
            exit;
        }

        if ($checkout_url !== null) {
            wp_redirect($checkout_url);
            exit;
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

/**
 * Decide whether a freshly submitted job needs to be routed through
 * checkout, and if so, hold it as a draft awaiting payment and start the
 * checkout session. Factored out of the admin-post handler so the decision
 * logic (unlike the redirect/exit handler itself) is directly testable.
 *
 * @param int    $job_id  The just-inserted job post ID.
 * @param string $plan    'free' | 'single' | 'monthly' (sanitized by caller).
 * @param string $gateway 'stripe' | 'paypal' (sanitized by caller).
 * @param int    $user_id The submitting user (job author).
 * @return string|null Redirect URL to the gateway, or null when the job
 *                      should be left on the existing free/pending path.
 * @throws Exception When checkout cannot be started (propagated from Remjobs_Checkout::start()).
 */
function remjobs_route_submitted_job(int $job_id, string $plan, string $gateway, int $user_id): ?string
{
    if ($plan === '' || $plan === 'free' || !class_exists('Remjobs_Plans') || !Remjobs_Plans::exists($plan)) {
        return null; // free: leave as 'pending' (existing behavior)
    }

    // Paid: hold as draft awaiting payment, then start checkout.
    wp_update_post(array('ID' => $job_id, 'post_status' => 'draft'));
    update_post_meta($job_id, '_remjobs_payment_status', 'awaiting');

    $payments = function_exists('remjobs_payments') ? remjobs_payments() : null;
    $checkout = $payments ? $payments->checkout() : null;
    if (!$checkout) {
        return null;
    }

    return $checkout->start($user_id, $job_id, $plan, $gateway); // throws on validation error
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

    // Check for success message - verify nonce first before accessing any parameters
    if (isset($_GET['remjobs_job_submitted']) && isset($_GET['_wpnonce'])) {
        // Verify the nonce FIRST before accessing any GET parameters
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        if (wp_verify_nonce($nonce, 'remjobs_job_submission')) {
            // Only now access the job_submitted parameter after nonce verification
            $job_submitted = sanitize_text_field(wp_unslash($_GET['remjobs_job_submitted']));

            if ($job_submitted === 'success') {
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
        }
        // If nonce verification fails, silently ignore (don't show success message)
    }

    if (!empty($output)) {
        echo wp_kses_post($output);
        return true;
    }

    return false;
}
?>
