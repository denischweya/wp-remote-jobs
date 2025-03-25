<?php

function remjobs_register_registration_block()
{
    register_block_type(__DIR__ . '/build', array(
        'render_callback' => 'remjobs_render_registration_block',
    ));
}
add_action('init', 'remjobs_register_registration_block');

function remjobs_render_registration_block()
{
    $company_name = isset($attributes['companyName']) ? esc_attr($attributes['companyName']) : '';
    $company_hq = isset($attributes['companyHQ']) ? esc_attr($attributes['companyHQ']) : '';
    $logo = isset($attributes['logo']) ? esc_url($attributes['logo']) : '';
    $website_url = isset($attributes['websiteURL']) ? esc_url($attributes['websiteURL']) : '';
    $email = isset($attributes['email']) ? sanitize_email($attributes['email']) : '';
    $description = isset($attributes['description']) ? wp_kses_post($attributes['description']) : '';

    $request_method = '';
    if (isset($_SERVER['REQUEST_METHOD'])) {
        $request_method = sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD']));
    }

    if ($request_method === 'POST') {
        // Sanitize and verify nonce
        $registration_nonce = isset($_POST['registration_nonce']) ? sanitize_text_field(wp_unslash($_POST['registration_nonce'])) : '';
        if (!wp_verify_nonce($registration_nonce, 'company_registration_action')) {
            wp_die(esc_html__('Security check failed. Please try again.', 'remote-jobs'));
        }

        // Handle file upload with proper sanitization
        $logo_url = '';
        if (isset($_FILES['logo']) && isset($_FILES['logo']['error']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Set up upload overrides
            $upload_overrides = array(
                'test_form' => false,
                'mimes' => array(
                    'jpg|jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif'
                )
            );

            // Handle the upload using WordPress functions
            $movefile = wp_handle_upload($_FILES['logo'], $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $logo_url = $movefile['url'];
            }
        }

        // Sanitize form data
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : '';
        $company_hq = isset($_POST['company_hq']) ? sanitize_text_field(wp_unslash($_POST['company_hq'])) : '';
        $website_url = isset($_POST['website_url']) ? esc_url_raw(wp_unslash($_POST['website_url'])) : '';
        $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';

        // Create user with sanitized data
        $user_id = wp_create_user($email, wp_generate_password(), $email);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'company_name', $company_name);
            update_user_meta($user_id, 'company_hq', $company_hq);
            update_user_meta($user_id, 'company_logo', esc_url_raw($logo_url));
            update_user_meta($user_id, 'company_website', $website_url);
            update_user_meta($user_id, 'company_description', $description);

            // Send notification email to user with their password
            wp_new_user_notification($user_id, null, 'user');

            // Redirect to login page
            wp_safe_redirect(wp_login_url() . '?registration=success');
            exit;
        } else {
            echo wp_kses_post('<p class="error">' . esc_html__('Registration failed. Please try again.', 'remote-jobs') . '</p>');
        }
    }

    ob_start();
    ?>
<div class="wp-block-remjobs-registration">
	<div class="registration-container">
		<h2 class="registration-title">
			<?php echo esc_html__('Company Registration', 'remote-jobs'); ?>
		</h2>
		<form id="registration-form" method="post" class="registration-form" enctype="multipart/form-data">
			<?php wp_nonce_field('company_registration_action', 'registration_nonce'); ?>
			<div class="form-group">
				<label
					for="company-name"><?php echo esc_html__('Company Name', 'remote-jobs'); ?></label>
				<input type="text" id="company-name" name="company_name"
					value="<?php echo esc_attr($company_name); ?>"
					required>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="company-hq"><?php echo esc_html__('Company HQ (Address)', 'remote-jobs'); ?></label>
					<input type="text" id="company-hq" name="company_hq"
						value="<?php echo esc_attr($company_hq); ?>"
						required>
				</div>
				<div class="form-group">
					<label
						for="website-url"><?php echo esc_html__('Company Website URL', 'remote-jobs'); ?></label>
					<input type="url" id="website-url" name="website_url"
						value="<?php echo esc_attr($website_url); ?>"
						required>
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="email"><?php echo esc_html__('Email Address', 'remote-jobs'); ?></label>
					<input type="email" id="email" name="email"
						value="<?php echo esc_attr($email); ?>"
						required>
				</div>
				<div class="form-group">
					<label
						for="logo"><?php echo esc_html__('Company Logo', 'remote-jobs'); ?></label>
					<div class="logo-upload-container">
						<input type="file" id="logo" name="logo" accept="image/*" class="file-upload">
						<?php if ($logo): ?>
						<div class="logo-preview">
							<img src="<?php echo esc_url($logo); ?>"
								alt="<?php echo esc_attr__('Company Logo Preview', 'remote-jobs'); ?>">
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
					for="description"><?php echo esc_html__('Company Description', 'remote-jobs'); ?></label>
				<textarea id="description" name="description" rows="4"
					required><?php echo esc_textarea($description); ?></textarea>
			</div>

			<button type="submit" class="submit-button">
				<?php echo esc_html__('Complete Registration', 'remote-jobs'); ?>
			</button>
		</form>
	</div>
</div>
<?php
    return ob_get_clean();
}

// Add Company Details section to user profile
function remjobs_add_company_fields($user)
{
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

// Save Company Details fields
function remjobs_save_company_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    // Properly sanitize and verify nonce
    $wpnonce = '';
    if (isset($_POST['_wpnonce'])) {
        $wpnonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
    }

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
}

// Add hooks to display and save the custom fields
add_action('show_user_profile', 'remjobs_add_company_fields');
add_action('edit_user_profile', 'remjobs_add_company_fields');
add_action('personal_options_update', 'remjobs_save_company_fields');
add_action('edit_user_profile_update', 'remjobs_save_company_fields');

?>