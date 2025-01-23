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
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (isset($_GET['job_submitted']) && $_GET['job_submitted'] === 'success' && wp_verify_nonce($nonce, 'job_submission')) {
        ?>
<div class="job-submission-success">
    <h2><?php esc_html_e('Job Submitted Successfully!', 'remote-jobs'); ?>
    </h2>
    <p><?php esc_html_e('Thank you for submitting your job listing. It will be reviewed by our team and published soon.', 'remote-jobs'); ?>
    </p>
    <p>
        <a href="<?php echo esc_url(remove_query_arg('job_submitted')); ?>"
            class="button submit-another-job">
            <?php esc_html_e('Submit Another Job', 'remote-jobs'); ?>
        </a>
    </p>
</div>

<?php
        return ob_get_clean();
    }

    // If no successful submission, show the form
    ?>
<form id="job-submission-form" method="post">
    <?php wp_nonce_field('submit_job_action', 'submit_job_nonce'); ?>

    <p class="required-fields">
        <?php esc_html_e('REQUIRED FIELDS *', 'remote-jobs'); ?>
    </p>

    <div class="form-group">
        <label
            for="job_title"><?php esc_html_e('Job Title *', 'remote-jobs'); ?></label>
        <?php
            // Sanitize and escape session data before output
            $job_title = isset($_SESSION['job_form_data']['job_title'])
                ? esc_attr(sanitize_text_field($_SESSION['job_form_data']['job_title']))
                : '';
    ?>
        <input type="text" id="job_title" name="job_title" required
            value="<?php echo esc_attr($job_title); ?>">
        <small><?php esc_html_e('Example: "Senior Designer". Titles must describe one position.', 'remote-jobs'); ?></small>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label
                for="job_category"><?php esc_html_e('Category *', 'remote-jobs'); ?></label>
            <select id="job_category" name="job_category" required>
                <option value="">
                    <?php esc_html_e('Select a category', 'remote-jobs'); ?>
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
                for="job_skills"><?php esc_html_e('Skills (If Applicable)', 'remote-jobs'); ?></label>
            <select id="job_skills" name="job_skills[]" multiple class="select2-multi select-skills" data-tags="true"
                data-token-separators='[","]'>
                <?php
                $skills = get_terms(['taxonomy' => 'job_skills', 'hide_empty' => false]);
    foreach ($skills as $skill) {
        echo '<option value="' . esc_attr($skill->term_id) . '">' . esc_html($skill->name) . '</option>';
    }
    ?>
            </select>
            <small><?php esc_html_e('Type to select existing skills or add new ones', 'remote-jobs'); ?></small>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label><?php esc_html_e('Is This Role Open Worldwide? *', 'remote-jobs'); ?></label>
            <small><?php esc_html_e('Selecting \'Yes\' means your future hire can work anywhere in the world without any location or time zone restrictions!', 'remote-jobs'); ?></small>
            <div class="radio-group">
                <label><input type="radio" name="worldwide" value="yes" required>
                    <?php esc_html_e('Yes', 'remote-jobs'); ?></label>
                <label><input type="radio" name="worldwide" value="no" required>
                    <?php esc_html_e('No', 'remote-jobs'); ?></label>
            </div>

            <div class="location-select" style="display: none;">
                <label
                    for="job_location"><?php esc_html_e('Job Location *', 'remote-jobs'); ?></label>
                <select id="job_location" name="job_location" class="select2-single">
                    <option value="">
                        <?php esc_html_e('Select Location', 'remote-jobs'); ?>
                    </option>
                    <?php
                    $locations = get_terms(['taxonomy' => 'job_location', 'hide_empty' => false]);
    foreach ($locations as $location) {
        echo '<option value="' . esc_attr($location->term_id) . '">' . esc_html($location->name) . '</option>';
    }
    ?>
                </select>
            </div>
        </div>

        <div class="form-group col-md-6">
            <label
                for="job_tags"><?php esc_html_e('Job Tags', 'remote-jobs'); ?></label>
            <select id="job_tags" name="job_tags[]" multiple class="select2-multi select-tags" data-tags="true"
                data-token-separators='[","]'>
                <?php
                $tags = get_terms(['taxonomy' => 'job_tags', 'hide_empty' => false]);
    foreach ($tags as $tag) {
        echo '<option value="' . esc_attr($tag->term_id) . '">' . esc_html($tag->name) . '</option>';
    }
    ?>
            </select>
            <small><?php esc_html_e('Type to select existing tags or add new ones', 'remote-jobs'); ?></small>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group col-md-6">
            <label
                for="salary_range"><?php esc_html_e('Salary Range', 'remote-jobs'); ?></label>
            <select id="salary_range" name="salary_range">
                <option value="">
                    <?php esc_html_e('Please Select', 'remote-jobs'); ?>
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
            <label><?php esc_html_e('Job Type *', 'remote-jobs'); ?></label>
            <div class="radio-group">
                <label><input type="radio" name="employment_type" value="full-time" required>
                    <?php esc_html_e('Full-Time', 'remote-jobs'); ?></label>
                <label><input type="radio" name="employment_type" value="contract" required>
                    <?php esc_html_e('Contract', 'remote-jobs'); ?></label>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label
            for="application_link"><?php esc_html_e('Application Link or Email *', 'remote-jobs'); ?></label>
        <input type="text" id="application_link" name="application_link" required>
        <small><?php esc_html_e('Link to Application page or Email address. (e.g mailto:example@example.com)', 'remote-jobs'); ?></small>
    </div>

    <div class="form-group">
        <label
            for="job_description"><?php esc_html_e('Job Description *', 'remote-jobs'); ?></label>
        <?php
            $job_description = isset($_SESSION['job_form_data']['job_description'])
                ? wp_kses_post($_SESSION['job_form_data']['job_description'])
                : '';

    wp_editor(
        $job_description,
        'job_description',
        array(
            'textarea_name' => 'job_description',
            'media_buttons' => false,
            'textarea_rows' => 50,
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
        value="<?php esc_attr_e('Submit Job', 'remote-jobs'); ?>">
</form>
<?php
    return ob_get_clean();
}

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