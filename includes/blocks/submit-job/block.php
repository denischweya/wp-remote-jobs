<?php
function render_submit_job_block($attributes, $content)
{
    wp_enqueue_script('submit-job-form', plugin_dir_url(__FILE__) . 'submit-job-form.js', array('jquery'), '1.0', true);
    wp_enqueue_style('submit-job-form', plugin_dir_url(__FILE__) . 'submit-job-form.css', array(), '1.0');

    ob_start();
    ?>
<form id="job-submission-form" method="post">
    <!-- Step 1: Job Details -->
    <div class="form-step" id="step1">
        <h2><?php esc_html_e('First, tell us about the position', 'your-text-domain'); ?>
        </h2>
        <p class="required-fields">
            <?php esc_html_e('REQUIRED FIELDS *', 'your-text-domain'); ?>
        </p>

        <div class="form-group">
            <label
                for="job_title"><?php esc_html_e('Job Title *', 'your-text-domain'); ?></label>
            <input type="text" id="job_title" name="job_title" required>
            <small><?php esc_html_e('Example: "Senior Designer". Titles must describe one position.', 'your-text-domain'); ?></small>
        </div>

        <div class="form-row">
            <div class="form-group col-md-6">
                <label
                    for="job_category"><?php esc_html_e('Category *', 'your-text-domain'); ?></label>
                <select id="job_category" name="job_category" required>
                    <option value="">
                        <?php esc_html_e('Design', 'your-text-domain'); ?>
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
                    for="job_skills"><?php esc_html_e('Skill (If Applicable)', 'your-text-domain'); ?></label>
                <select id="job_skills" name="job_skills[]" multiple class="select2-multi select-skills">
                    <option value="">
                        <?php esc_html_e('Select all skills that apply', 'your-text-domain'); ?>
                    </option>
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
            '',
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

        <button type="button"
            class="next-step"><?php esc_html_e('Continue to Step 2', 'your-text-domain'); ?></button>
    </div>

    <!-- Step 2: Company Details -->
    <div class="form-step" id="step2" style="display: none;">
        <h2><?php esc_html_e('Tell Us More About Your Company', 'your-text-domain'); ?>
        </h2>
        <p><?php esc_html_e('Posted before? Just enter your email, all other info will be pulled in from your last position!', 'your-text-domain'); ?>
        </p>

        <div class="form-group">
            <label
                for="company_name"><?php esc_html_e('Company Name *', 'your-text-domain'); ?></label>
            <input type="text" id="company_name" name="company_name" required>
            <small><?php esc_html_e('Enter your company or organization\'s name.', 'your-text-domain'); ?></small>
        </div>

        <div class="form-group">
            <label
                for="company_hq"><?php esc_html_e('Company HQ *', 'your-text-domain'); ?></label>
            <input type="text" id="company_hq" name="company_hq" required>
            <small><?php esc_html_e('Where your company is officially headquartered.', 'your-text-domain'); ?></small>
        </div>

        <div class="form-group">
            <label
                for="company_logo"><?php esc_html_e('Logo *', 'your-text-domain'); ?></label>
            <input type="file" id="company_logo" name="company_logo" accept="image/*" required>
            <small><?php esc_html_e('It\'s highly recommended to use your Twitter or Facebook avatar. Optional — Your company logo will appear at the top of your listing and live profile.', 'your-text-domain'); ?></small>
        </div>

        <div class="form-group">
            <label
                for="company_website"><?php esc_html_e('Company\'s Website URL *', 'your-text-domain'); ?></label>
            <input type="url" id="company_website" name="company_website" required>
            <small><?php esc_html_e('Example: https://mybusiness.com/', 'your-text-domain'); ?></small>
        </div>

        <div class="form-group">
            <label
                for="company_email"><?php esc_html_e('Email *', 'your-text-domain'); ?></label>
            <input type="email" id="company_email" name="company_email" required>
            <small><?php esc_html_e('We\'ll send your receipt and confirmation email here.', 'your-text-domain'); ?></small>
        </div>

        <div class="form-group">
            <label
                for="company_description"><?php esc_html_e('Company Description', 'your-text-domain'); ?></label>
            <?php
                wp_editor('', 'company_description', array(
                    'media_buttons' => false,
                    'textarea_rows' => 10,
                    'teeny' => true,
                    'quicktags' => array('buttons' => 'strong,em,link,ul,ol,li'),
                ));
    ?>
        </div>

        <button type="button"
            class="prev-step"><?php esc_html_e('Back to Step 1', 'your-text-domain'); ?></button>
        <input type="submit"
            value="<?php esc_attr_e('Submit Job', 'your-text-domain'); ?>">
    </div>

    <?php wp_nonce_field('submit_job', 'job_nonce'); ?>
</form>
<?php
    return ob_get_clean();
}

function handle_job_submission()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_nonce']) && wp_verify_nonce($_POST['job_nonce'], 'submit_job')) {
        // Handle file upload
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        $uploaded_file = $_FILES['company_logo'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploaded_file, $upload_overrides);

        // Create user
        $user_id = wp_create_user(
            sanitize_text_field($_POST['company_name']),
            wp_generate_password(),
            sanitize_email($_POST['company_email'])
        );

        if (!is_wp_error($user_id)) {
            // Update user meta
            update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
            update_user_meta($user_id, 'company_hq', sanitize_text_field($_POST['company_hq']));
            update_user_meta($user_id, 'company_logo', $movefile['url']);
            update_user_meta($user_id, 'company_website', esc_url_raw($_POST['company_website']));
            update_user_meta($user_id, 'company_description', wp_kses_post($_POST['company_description']));

            // Send password reset link
            wp_new_user_notification($user_id, null, 'user');

            // Create job post
            $job_id = wp_insert_post(array(
                'post_title'   => sanitize_text_field($_POST['job_title']),
                'post_content' => wp_kses_post($_POST['job_description']),
                'post_status'  => 'pending',
                'post_type'    => 'jobs',
                'post_author'  => $user_id,
            ));

            if ($job_id) {
                // Set taxonomies
                wp_set_object_terms($job_id, intval($_POST['job_category']), 'job_category');
                wp_set_object_terms($job_id, array_map('intval', $_POST['job_skills']), 'job_skills');
                wp_set_object_terms($job_id, intval($_POST['employment_type']), 'employment_type');
                wp_set_object_terms($job_id, array_map('intval', $_POST['benefits']), 'benefits');

                if ($_POST['worldwide'] === 'no') {
                    $job_location = intval($_POST['job_location']);
                    wp_set_object_terms($job_id, $job_location, 'job_location');
                }

                // Set custom fields
                update_post_meta($job_id, '_worldwide', sanitize_text_field($_POST['worldwide']));
                update_post_meta($job_id, '_salary_range', sanitize_text_field($_POST['salary_range']));
                update_post_meta($job_id, '_how_to_apply', sanitize_textarea_field($_POST['how_to_apply']));

                // Set salary range taxonomy
                wp_set_object_terms($job_id, intval($_POST['salary_range']), 'salary_range');

                wp_redirect(add_query_arg('job_submitted', 'success', wp_get_referer()));
                exit;
            }
        }
    }
}
add_action('template_redirect', 'handle_job_submission');

// Add custom user fields
function add_custom_user_fields($user)
{
    ?>
<h3><?php esc_html_e('Company Information', 'your-text-domain'); ?>
</h3>
<table class="form-table">
    <tr>
        <th><label
                for="company_name"><?php esc_html_e('Company Name', 'your-text-domain'); ?></label>
        </th>
        <td><input type="text" name="company_name" id="company_name"
                value="<?php echo esc_attr(get_user_meta($user->ID, 'company_name', true)); ?>"
                class="regular-text" /></td>
    </tr>
    <tr>
        <th><label
                for="company_hq"><?php esc_html_e('Company HQ', 'your-text-domain'); ?></label>
        </th>
        <td><input type="text" name="company_hq" id="company_hq"
                value="<?php echo esc_attr(get_user_meta($user->ID, 'company_hq', true)); ?>"
                class="regular-text" /></td>
    </tr>
    <tr>
        <th><label
                for="company_website"><?php esc_html_e('Company Website', 'your-text-domain'); ?></label>
        </th>
        <td><input type="url" name="company_website" id="company_website"
                value="<?php echo esc_url(get_user_meta($user->ID, 'company_website', true)); ?>"
                class="regular-text" /></td>
    </tr>
    <tr>
        <th><label
                for="company_description"><?php esc_html_e('Company Description', 'your-text-domain'); ?></label>
        </th>
        <td><?php wp_editor(get_user_meta($user->ID, 'company_description', true), 'company_description', array('textarea_rows' => 5)); ?>
        </td>
    </tr>
</table>
<?php
}
add_action('show_user_profile', 'add_custom_user_fields');
add_action('edit_user_profile', 'add_custom_user_fields');

// Save custom user fields
function save_custom_user_fields($user_id)
{
    if (current_user_can('edit_user', $user_id)) {
        update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
        update_user_meta($user_id, 'company_hq', sanitize_text_field($_POST['company_hq']));
        update_user_meta($user_id, 'company_website', esc_url_raw($_POST['company_website']));
        update_user_meta($user_id, 'company_description', wp_kses_post($_POST['company_description']));
    }
}
add_action('personal_options_update', 'save_custom_user_fields');
add_action('edit_user_profile_update', 'save_custom_user_fields');
?>