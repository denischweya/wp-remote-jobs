<?php
/**
 * Registration Block Handler
 *
 * @package RemJobs
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the registration block.
 */
function remjobs_register_registration_block()
{
    register_block_type(__DIR__ . '/build', array(
        'render_callback' => 'remjobs_render_registration_block',
    ));
}
add_action('init', 'remjobs_register_registration_block');

/**
 * Process the registration form submission.
 * Separate from the render function for better performance.
 */
function remjobs_process_registration_form()
{
    // Only process on actual form submission
    if (
        !isset($_SERVER['REQUEST_METHOD']) ||
        $_SERVER['REQUEST_METHOD'] !== 'POST' ||
        empty($_POST['remjobs_registration_submit'])
    ) {
        return;
    }

    // Verify nonce
    if (!isset($_POST['remjobs_registration_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['remjobs_registration_nonce'])), 'remjobs_registration_action')) {
        wp_die(esc_html__('Security check failed. Please try again.', 'remote-jobs'), esc_html__('Security Error', 'remote-jobs'), array('response' => 403));
    }

    // Check honeypot field (simple anti-spam measure)
    if (!empty($_POST['remjobs_website_hp'])) {
        // Likely a bot, but don't reveal this - just redirect back
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }

    // Sanitize form data
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
    $company_hq = isset($_POST['company_hq']) ? sanitize_text_field(wp_unslash($_POST['company_hq'])) : '';
    $website_url = isset($_POST['website_url']) ? esc_url_raw(wp_unslash($_POST['website_url'])) : '';
    $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';

    // Validate required fields
    $errors = array();
    if (empty($email)) {
        $errors[] = esc_html__('Email address is required.', 'remote-jobs');
    } elseif (!is_email($email)) {
        $errors[] = esc_html__('Please enter a valid email address.', 'remote-jobs');
    }

    if (empty($company_name)) {
        $errors[] = esc_html__('Company name is required.', 'remote-jobs');
    }

    if (!empty($website_url) && !filter_var($website_url, FILTER_VALIDATE_URL)) {
        $errors[] = esc_html__('Please enter a valid website URL.', 'remote-jobs');
    }

    if (!empty($errors)) {
        // Store errors in transient for display
        set_transient('remjobs_registration_errors_' . wp_get_session_token(), $errors, 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }

    // Handle file upload with proper sanitization
    $logo_url = '';
    // Check if a file was uploaded and there are no errors
    if (
        isset($_FILES['logo']) &&
        isset($_FILES['logo']['error']) &&
        isset($_FILES['logo']['name']) &&
        intval($_FILES['logo']['error']) === UPLOAD_ERR_OK &&
        !empty($_FILES['logo']['name'])
    ) {
        // Sanitize the filename
        $file_name = sanitize_file_name(basename(sanitize_text_field($_FILES['logo']['name'])));

        // Check for valid mime type
        $file_type = wp_check_filetype($file_name);
        if (!$file_type['type'] || !in_array($file_type['type'], array('image/jpeg', 'image/png', 'image/gif'), true)) {
            // Invalid file type
            set_transient(
                'remjobs_registration_errors_' . wp_get_session_token(),
                array(esc_html__('Invalid file type. Please upload a JPEG, PNG, or GIF image.', 'remote-jobs')),
                5 * MINUTE_IN_SECONDS
            );
            wp_safe_redirect(wp_get_referer() ?: home_url());
            exit;
        }

        // Set up upload overrides
        $upload_overrides = array(
            'test_form' => false,
            'mimes' => array(
                'jpg|jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif'
            )
        );

        // Create a file array with sanitized values
        $file = array(
            'name'     => $file_name,
            'type'     => isset($_FILES['logo']['type']) ? sanitize_text_field($_FILES['logo']['type']) : '',
            'tmp_name' => isset($_FILES['logo']['tmp_name']) ? sanitize_text_field($_FILES['logo']['tmp_name']) : '',
            'error'    => isset($_FILES['logo']['error']) ? intval($_FILES['logo']['error']) : UPLOAD_ERR_NO_FILE,
            'size'     => isset($_FILES['logo']['size']) ? intval($_FILES['logo']['size']) : 0
        );

        // Handle the upload using WordPress functions
        $movefile = wp_handle_upload($file, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            $logo_url = esc_url_raw($movefile['url']);
        } else {
            // Upload failed
            set_transient(
                'remjobs_registration_errors_' . wp_get_session_token(),
                array(isset($movefile['error']) ? esc_html($movefile['error']) : esc_html__('File upload failed.', 'remote-jobs')),
                5 * MINUTE_IN_SECONDS
            );
            wp_safe_redirect(wp_get_referer() ?: home_url());
            exit;
        }
    }

    // Check if user already exists
    if (email_exists($email)) {
        set_transient(
            'remjobs_registration_errors_' . wp_get_session_token(),
            array(esc_html__('This email address is already registered. Please use a different email.', 'remote-jobs')),
            5 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }

    // Create user with sanitized data
    $user_id = wp_create_user($email, wp_generate_password(), $email);

    if (!is_wp_error($user_id)) {
        // Apply appropriate role
        $user = new WP_User($user_id);
        $user->set_role('subscriber');  // Or a custom role if defined

        // Store company details as user meta
        update_user_meta($user_id, 'company_name', $company_name);
        update_user_meta($user_id, 'company_hq', $company_hq);
        if (!empty($logo_url)) {
            update_user_meta($user_id, 'company_logo', esc_url_raw($logo_url));
        }
        update_user_meta($user_id, 'company_website', $website_url);
        update_user_meta($user_id, 'company_description', $description);

        // Send notification email to user with their password
        wp_new_user_notification($user_id, null, 'user');

        // Store success message
        set_transient(
            'remjobs_registration_success',
            esc_html__('Registration successful! Please check your email for login details.', 'remote-jobs'),
            5 * MINUTE_IN_SECONDS
        );

        // Redirect to login page
        wp_safe_redirect(wp_login_url() . '?registration=success');
        exit;
    } else {
        // Registration failed
        set_transient(
            'remjobs_registration_errors_' . wp_get_session_token(),
            array($user_id->get_error_message()),
            5 * MINUTE_IN_SECONDS
        );
        wp_safe_redirect(wp_get_referer() ?: home_url());
        exit;
    }
}
add_action('init', 'remjobs_process_registration_form');

/**
 * Render the registration block.
 *
 * @param array $attributes Block attributes.
 * @return string Block HTML.
 */
function remjobs_render_registration_block($attributes = array())
{
    // Redirect logged-in users
    if (is_user_logged_in()) {
        return '<div class="wp-block-remjobs-registration">' .
            esc_html__('You are already registered and logged in.', 'remote-jobs') .
            '</div>';
    }

    // Extract block attributes with defaults
    $company_name = isset($attributes['companyName']) ? esc_attr($attributes['companyName']) : '';
    $company_hq = isset($attributes['companyHQ']) ? esc_attr($attributes['companyHQ']) : '';
    $logo = isset($attributes['logo']) ? esc_url($attributes['logo']) : '';
    $website_url = isset($attributes['websiteURL']) ? esc_url($attributes['websiteURL']) : '';
    $email = isset($attributes['email']) ? sanitize_email($attributes['email']) : '';
    $description = isset($attributes['description']) ? wp_kses_post($attributes['description']) : '';

    // Get any error messages
    $error_messages = '';
    $success_message = '';
    $session_token = wp_get_session_token();

    $errors = get_transient('remjobs_registration_errors_' . $session_token);
    if ($errors) {
        delete_transient('remjobs_registration_errors_' . $session_token);
        $error_messages = '<div class="remjobs-error-messages">';
        foreach ($errors as $error) {
            $error_messages .= '<p class="error">' . $error . '</p>';
        }
        $error_messages .= '</div>';
    }

    $success = get_transient('remjobs_registration_success');
    if ($success) {
        delete_transient('remjobs_registration_success');
        $success_message = '<div class="remjobs-success-message"><p>' . $success . '</p></div>';
    }

    ob_start();
    ?>
<div class="wp-block-remjobs-registration">
	<?php
    echo wp_kses_post($error_messages);
    echo wp_kses_post($success_message);
    ?>
	<div class="registration-container">
		<h2 class="registration-title">
			<?php echo esc_html__('Company Registration', 'remote-jobs'); ?>
		</h2>
		<form id="registration-form" method="post" class="registration-form" enctype="multipart/form-data">
			<?php
            // Security field
            wp_nonce_field('remjobs_registration_action', 'remjobs_registration_nonce');
    ?>

			<!-- Honeypot field to catch bots -->
			<div class="honeypot-field" style="position: absolute; left: -9999px; top: -9999px;">
				<input type="text" name="remjobs_website_hp" value="" tabindex="-1" autocomplete="off">
			</div>

			<div class="form-group">
				<label
					for="company-name"><?php echo esc_html__('Company Name', 'remote-jobs'); ?>
					<span class="required">*</span></label>
				<input type="text" id="company-name" name="company_name"
					value="<?php echo esc_attr($company_name); ?>"
					required>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="company-hq"><?php echo esc_html__('Company HQ (Address)', 'remote-jobs'); ?>
						<span class="required">*</span></label>
					<input type="text" id="company-hq" name="company_hq"
						value="<?php echo esc_attr($company_hq); ?>"
						required>
				</div>
				<div class="form-group">
					<label
						for="website-url"><?php echo esc_html__('Company Website URL', 'remote-jobs'); ?></label>
					<input type="url" id="website-url" name="website_url"
						value="<?php echo esc_attr($website_url); ?>">
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="email"><?php echo esc_html__('Email Address', 'remote-jobs'); ?>
						<span class="required">*</span></label>
					<input type="email" id="email" name="email"
						value="<?php echo esc_attr($email); ?>"
						required>
					<span
						class="field-hint"><?php echo esc_html__('This will be your username for logging in', 'remote-jobs'); ?></span>
				</div>
				<div class="form-group">
					<label
						for="logo"><?php echo esc_html__('Company Logo', 'remote-jobs'); ?></label>
					<div class="logo-upload-container">
						<input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif"
							class="file-upload">
						<span
							class="field-hint"><?php echo esc_html__('Accepted formats: JPEG, PNG, GIF', 'remote-jobs'); ?></span>
						<?php if ($logo): ?>
						<div class="logo-preview">
							<?php
							// Check if logo is a WordPress attachment ID
							if (is_numeric($logo) && wp_attachment_is_image($logo)) {
								echo wp_get_attachment_image($logo, 'thumbnail', false, array(
									'alt' => esc_attr__('Company Logo Preview', 'remote-jobs'),
									'class' => 'company-logo-preview'
								));
							} else {
								// Fallback for URL-based logos (for backward compatibility)
								printf(
									'<img src="%s" alt="%s" class="company-logo-preview" style="max-width: 150px; height: auto;">',
									esc_url($logo),
									esc_attr__('Company Logo Preview', 'remote-jobs')
								);
							}
							?>
							<button type="button" class="button remove-logo-button">
								<?php echo esc_html__('Remove Logo', 'remote-jobs'); ?>
							</button>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="form-group">
				<label
					for="description"><?php echo esc_html__('Company Description', 'remote-jobs'); ?>
					<span class="required">*</span></label>
				<textarea id="description" name="description" rows="4"
					required><?php echo esc_textarea($description); ?></textarea>
			</div>

			<input type="hidden" name="remjobs_registration_submit" value="1">

			<button type="submit" class="submit-button">
				<?php echo esc_html__('Complete Registration', 'remote-jobs'); ?>
			</button>
		</form>
	</div>
</div>
<?php
    return ob_get_clean();
}

/**
 * Add Company Details section to user profile
 *
 * @param WP_User $user User object.
 */
function remjobs_add_company_fields($user)
{
    // Check if user has permission to edit users
    if (!current_user_can('edit_user', $user->ID)) {
        return;
    }
    ?>
<h3><?php echo esc_html__('Company Details', 'remote-jobs'); ?>
</h3>
<table class="form-table">
	<tr>
		<th><label
				for="company_name"><?php echo esc_html__('Company Name', 'remote-jobs'); ?></label>
		</th>
		<td>
			<input type="text" name="company_name" id="company_name"
				value="<?php echo esc_attr(get_user_meta($user->ID, 'company_name', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_hq"><?php echo esc_html__('Company HQ', 'remote-jobs'); ?></label>
		</th>
		<td>
			<input type="text" name="company_hq" id="company_hq"
				value="<?php echo esc_attr(get_user_meta($user->ID, 'company_hq', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_logo"><?php echo esc_html__('Logo URL', 'remote-jobs'); ?></label>
		</th>
		<td>
			<input type="url" name="company_logo" id="company_logo"
				value="<?php echo esc_url(get_user_meta($user->ID, 'company_logo', true)); ?>"
				class="regular-text" />
			<?php 
			$company_logo = get_user_meta($user->ID, 'company_logo', true);
			if ($company_logo): ?>
			<div class="company-logo-display">
				<?php
				// Check if logo is a WordPress attachment ID
				if (is_numeric($company_logo) && wp_attachment_is_image($company_logo)) {
					echo wp_get_attachment_image($company_logo, 'thumbnail', false, array(
						'alt' => esc_attr__('Company Logo', 'remote-jobs'),
						'class' => 'company-logo-profile'
					));
				} else {
					// Fallback for URL-based logos (for backward compatibility)
					printf(
						'<img src="%s" alt="%s" class="company-logo-profile" style="max-width: 100px; height: auto;">',
						esc_url($company_logo),
						esc_attr__('Company Logo', 'remote-jobs')
					);
				}
				?>
			</div>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label
				for="company_website"><?php echo esc_html__('Company Website URL', 'remote-jobs'); ?></label>
		</th>
		<td>
			<input type="url" name="company_website" id="company_website"
				value="<?php echo esc_url(get_user_meta($user->ID, 'company_website', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_description"><?php echo esc_html__('Company Description', 'remote-jobs'); ?></label>
		</th>
		<td>
			<textarea name="company_description" id="company_description" rows="5"
				cols="30"><?php echo esc_textarea(get_user_meta($user->ID, 'company_description', true)); ?></textarea>
		</td>
	</tr>
</table>
<?php
}

/**
 * Save Company Details fields
 *
 * @param int $user_id ID of the user being edited.
 * @return bool False if current user cannot edit the user.
 */
function remjobs_save_company_fields($user_id)
{
    // Security check: Verify user has permission
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    // Security check: Verify nonce
    $wpnonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';

    if (!wp_verify_nonce($wpnonce, 'update-user_' . $user_id)) {
        return false;
    }

    // Sanitize and update user meta with proper checks
    if (isset($_POST['company_name'])) {
        update_user_meta($user_id, 'company_name', sanitize_text_field(wp_unslash($_POST['company_name'])));
    }

    if (isset($_POST['company_hq'])) {
        update_user_meta($user_id, 'company_hq', sanitize_text_field(wp_unslash($_POST['company_hq'])));
    }

    if (isset($_POST['company_logo'])) {
        update_user_meta($user_id, 'company_logo', esc_url_raw(wp_unslash($_POST['company_logo'])));
    }

    if (isset($_POST['company_website'])) {
        update_user_meta($user_id, 'company_website', esc_url_raw(wp_unslash($_POST['company_website'])));
    }

    if (isset($_POST['company_description'])) {
        update_user_meta($user_id, 'company_description', wp_kses_post(wp_unslash($_POST['company_description'])));
    }

    return true;
}

// Add hooks to display and save the custom fields
add_action('show_user_profile', 'remjobs_add_company_fields');
add_action('edit_user_profile', 'remjobs_add_company_fields');
add_action('personal_options_update', 'remjobs_save_company_fields');
add_action('edit_user_profile_update', 'remjobs_save_company_fields');

?>