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
        wp_redirect(wp_login_url(get_permalink()));
        exit;
    }

    // Start the session if it hasn't been started already
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    ob_start();

    // Check if we have a successful submission
    if (isset($_GET['job_submitted']) && $_GET['job_submitted'] === 'success') {
        ?>
<div class="job-submission-success">
    <h2><?php esc_html_e('Job Submitted Successfully!', 'your-text-domain'); ?>
    </h2>
    <p><?php esc_html_e('Thank you for submitting your job listing. It will be reviewed by our team and published soon.', 'your-text-domain'); ?>
    </p>
    <p>
        <a href="<?php echo esc_url(remove_query_arg('job_submitted')); ?>"
            class="button submit-another-job">
            <?php esc_html_e('Submit Another Job', 'your-text-domain'); ?>
        </a>
    </p>
</div>
<style>
    .job-submission-success {
        background: #f0f9ff;
        border: 1px solid #3262f9;
        border-radius: 4px;
        padding: 20px;
        margin: 20px 0;
        text-align: center;
    }

    .job-submission-success h2 {
        color: #3262f9;
        margin-top: 0;
    }

    .submit-another-job {
        display: inline-block;
        background: #3262f9;
        color: #fff;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 4px;
        margin-top: 15px;
    }

    .submit-another-job:hover {
        background: #2451d1;
        color: #fff;
    }
</style>
<?php
        return ob_get_clean();
    }

    // If no successful submission, show the form
    ?>
<form id="job-submission-form" method="post">
    <h2><?php esc_html_e('Submit a New Job', 'your-text-domain'); ?>
    </h2>
    <p class="required-fields">
        <?php esc_html_e('REQUIRED FIELDS *', 'your-text-domain'); ?>
    </p>

    <div class="form-group">
        <label
            for="job_title"><?php esc_html_e('Job Title *', 'your-text-domain'); ?></label>
        <input type="text" id="job_title" name="job_title" required
            value="<?php echo esc_attr($_SESSION['job_form_data']['job_title'] ?? ''); ?>">
        <small><?php esc_html_e('Example: "Senior Designer". Titles must describe one position.', 'your-text-domain'); ?></small>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label
                for="job_category"><?php esc_html_e('Category *', 'your-text-domain'); ?></label>
            <select id="job_category" name="job_category" required>
                <option value="">
                    <?php esc_html_e('Select a category', 'your-text-domain'); ?>
                </option>
                <?php
                    $categories = get_terms(['taxonomy' => 'job_category', 'hide_empty' => false]);
    foreach ($categories as $category) {
        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
    }
    ?>
            </select>
        </div>
        <div class="form-group col-md-6">
            <label
                for="job_skills"><?php esc_html_e('Skills (If Applicable)', 'your-text-domain'); ?></label>
            <select id="job_skills" name="job_skills[]" multiple class="select2-multi select-skills">
                <?php
    $skills = get_terms(['taxonomy' => 'job_skills', 'hide_empty' => false]);
    foreach ($skills as $skill) {
        echo '<option value="' . esc_attr($skill->term_id) . '">' . esc_html($skill->name) . '</option>';
    }
    ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label><?php esc_html_e('Is This Role Open Worldwide? *', 'your-text-domain'); ?></label>
        <small><?php esc_html_e('Selecting \'Yes\' means your future hire can work anywhere in the world without any location or time zone restrictions!', 'your-text-domain'); ?></small>
        <div class="radio-group">
            <label><input type="radio" name="worldwide" value="yes" required>
                <?php esc_html_e('Yes', 'your-text-domain'); ?></label>
            <label><input type="radio" name="worldwide" value="no" required>
                <?php esc_html_e('No', 'your-text-domain'); ?></label>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label
                for="salary_range"><?php esc_html_e('Salary Range', 'your-text-domain'); ?></label>
            <select id="salary_range" name="salary_range">
                <option value="">
                    <?php esc_html_e('Please Select', 'your-text-domain'); ?>
                </option>
                <?php
    $salary_ranges = get_terms(['taxonomy' => 'salary_range', 'hide_empty' => false]);
    foreach ($salary_ranges as $range) {
        echo '<option value="' . esc_attr($range->term_id) . '">' . esc_html($range->name) . '</option>';
    }
    ?>
            </select>
        </div>
        <div class="form-group col-md-6">
            <label><?php esc_html_e('Job Type *', 'your-text-domain'); ?></label>
            <div class="radio-group">
                <label><input type="radio" name="employment_type" value="full-time" required>
                    <?php esc_html_e('Full-Time', 'your-text-domain'); ?></label>
                <label><input type="radio" name="employment_type" value="contract" required>
                    <?php esc_html_e('Contract', 'your-text-domain'); ?></label>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label
            for="application_link"><?php esc_html_e('Application Link or Email *', 'your-text-domain'); ?></label>
        <input type="text" id="application_link" name="application_link" required>
        <small><?php esc_html_e('Link to Application page or Email address.', 'your-text-domain'); ?></small>
    </div>

    <div class="form-group">
        <label
            for="job_description"><?php esc_html_e('Job Description *', 'your-text-domain'); ?></label>
        <?php
            wp_editor(
                $_SESSION['job_form_data']['job_description'] ?? '',
                'job_description',
                array(
                                                                                'textarea_name' => 'job_description',
                                                                                'media_buttons' => false,
                                                                                'textarea_rows' => 10,
                                                                                'teeny' => true,
                                                                                'quicktags' => array('buttons' => 'strong,em,link,ul,ol,li'),
                                                                                'tinymce' => array(
                                                                            'toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink',
                                                                            'toolbar2' => '',
                                                                                ),
                                                                            )
            );
    ?>
    </div>

    <input type="submit"
        value="<?php esc_attr_e('Submit Job', 'your-text-domain'); ?>">

    <?php wp_nonce_field('submit_job', 'job_nonce'); ?>
</form>
<?php
    return ob_get_clean();
}

function handle_job_submission()
{
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Store form data in session
        $_SESSION['job_form_data'] = $_POST;

        if (isset($_POST['job_nonce']) && wp_verify_nonce($_POST['job_nonce'], 'submit_job')) {
            // Create job post
            $job_id = wp_insert_post(array(
                'post_title'   => sanitize_text_field($_POST['job_title']),
                'post_content' => wp_kses_post($_POST['job_description']),
                'post_status'  => 'pending',
                'post_type'    => 'jobs',
                'post_author'  => get_current_user_id(),
            ));

            if ($job_id) {
                // Set taxonomies
                if (isset($_POST['job_category'])) {
                    wp_set_object_terms($job_id, intval($_POST['job_category']), 'job_category');
                }

                if (isset($_POST['job_skills']) && is_array($_POST['job_skills'])) {
                    wp_set_object_terms($job_id, array_map('intval', $_POST['job_skills']), 'job_skills');
                }

                if (isset($_POST['employment_type'])) {
                    wp_set_object_terms($job_id, sanitize_text_field($_POST['employment_type']), 'employment_type');
                }

                // Only set job_benefits if it exists
                if (isset($_POST['job_benefits']) && is_array($_POST['job_benefits'])) {
                    wp_set_object_terms($job_id, array_map('intval', $_POST['job_benefits']), 'job_benefits');
                }

                if (isset($_POST['worldwide']) && $_POST['worldwide'] === 'no' && isset($_POST['job_location'])) {
                    $job_location = intval($_POST['job_location']);
                    wp_set_object_terms($job_id, $job_location, 'job_location');
                }

                // Set custom fields
                update_post_meta($job_id, '_worldwide', sanitize_text_field($_POST['worldwide']));

                if (isset($_POST['salary_range'])) {
                    update_post_meta($job_id, '_salary_range', sanitize_text_field($_POST['salary_range']));
                    wp_set_object_terms($job_id, intval($_POST['salary_range']), 'salary_range');
                }

                if (isset($_POST['how_to_apply'])) {
                    update_post_meta($job_id, '_how_to_apply', sanitize_textarea_field($_POST['how_to_apply']));
                }

                if (isset($_POST['application_link'])) {
                    update_post_meta($job_id, '_application_link', sanitize_text_field($_POST['application_link']));
                }

                // Clear the session data after successful submission
                unset($_SESSION['job_form_data']);

                // Redirect with success parameter
                $redirect_url = add_query_arg('job_submitted', 'success', wp_get_referer());
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}
add_action('template_redirect', 'handle_job_submission');

// Add a function to clear session data when the job is successfully submitted
function clear_job_session_data()
{
    if (isset($_GET['job_submitted']) && $_GET['job_submitted'] === 'success') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['job_form_data']);
    }
}
add_action('template_redirect', 'clear_job_session_data');

// Optional: Add JavaScript to prevent form resubmission on page refresh
function add_form_submission_script()
{
    ?>
<script>
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }
</script>
<?php
}
add_action('wp_footer', 'add_form_submission_script');
?>