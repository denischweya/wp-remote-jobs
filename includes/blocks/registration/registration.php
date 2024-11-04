<?php


function render_registration_block($attributes, $content)
{
    $company_name = isset($attributes['companyName']) ? esc_attr($attributes['companyName']) : '';
    $company_hq = isset($attributes['companyHQ']) ? esc_attr($attributes['companyHQ']) : '';
    $logo = isset($attributes['logo']) ? esc_url($attributes['logo']) : '';
    $website_url = isset($attributes['websiteURL']) ? esc_url($attributes['websiteURL']) : '';
    $email = isset($attributes['email']) ? sanitize_email($attributes['email']) : '';
    $description = isset($attributes['description']) ? wp_kses_post($attributes['description']) : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle file upload
        $logo_url = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $file_name = sanitize_file_name($_FILES['logo']['name']);
            $file_path = $upload_dir['path'] . '/' . $file_name;

            // Move uploaded file
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $file_path)) {
                $logo_url = $upload_dir['url'] . '/' . $file_name;
            }
        }

        // Process form submission
        $user_id = wp_create_user($_POST['email'], wp_generate_password(), $_POST['email']);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
            update_user_meta($user_id, 'company_hq', sanitize_text_field($_POST['company_hq']));
            update_user_meta($user_id, 'company_logo', esc_url_raw($logo_url));
            update_user_meta($user_id, 'company_website', esc_url_raw($_POST['website_url']));
            update_user_meta($user_id, 'company_description', wp_kses_post($_POST['description']));

            // Send notification email to user with their password
            wp_new_user_notification($user_id, null, 'user');

            // Redirect to login page
            wp_safe_redirect(wp_login_url() . '?registration=success');
            exit;
        } else {
            echo '<p class="error">' . __('Registration failed. Please try again.', 'wp-remote-jobs') . '</p>';
        }
    }

    ob_start();
    ?>
<div class="wp-block-wp-remote-jobs-registration">
	<div class="registration-container">
		<h2 class="registration-title">
			<?php esc_html_e('Company Registration', 'registration'); ?>
		</h2>
		<form id="registration-form" method="post" class="registration-form" enctype="multipart/form-data">
			<div class="form-group">
				<label
					for="company-name"><?php esc_html_e('Company Name', 'registration'); ?></label>
				<input type="text" id="company-name" name="company_name"
					value="<?php echo $company_name; ?>" required>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="company-hq"><?php esc_html_e('Company HQ (Address)', 'registration'); ?></label>
					<input type="text" id="company-hq" name="company_hq"
						value="<?php echo $company_hq; ?>" required>
				</div>
				<div class="form-group">
					<label
						for="website-url"><?php esc_html_e('Company Website URL', 'registration'); ?></label>
					<input type="url" id="website-url" name="website_url"
						value="<?php echo $website_url; ?>" required>
				</div>
			</div>

			<div class="form-row">
				<div class="form-group">
					<label
						for="email"><?php esc_html_e('Email Address', 'registration'); ?></label>
					<input type="email" id="email" name="email"
						value="<?php echo $email; ?>" required>
				</div>
				<div class="form-group">
					<label
						for="logo"><?php esc_html_e('Company Logo', 'registration'); ?></label>
					<div class="logo-upload-container">
						<input type="file" id="logo" name="logo" accept="image/*" class="file-upload">
						<?php if ($logo): ?>
						<div class="logo-preview">
							<img src="<?php echo esc_url($logo); ?>"
								alt="Company Logo Preview">
							<button type="button" class="button remove-logo-button">
								<?php esc_html_e('Remove Logo', 'registration'); ?>
							</button>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="form-group">
				<label
					for="description"><?php esc_html_e('Company Description', 'registration'); ?></label>
				<textarea id="description" name="description" rows="4"
					required><?php echo $description; ?></textarea>
			</div>

			<button type="submit" class="submit-button">
				<?php esc_html_e('Complete Registration', 'registration'); ?>
			</button>
		</form>
	</div>
</div>
<?php
    return ob_get_clean();
}

// Add Company Details section to user profile
function add_company_details_fields($user)
{
    ?>
<h3><?php _e('Company Details', 'wp-remote-jobs'); ?>
</h3>
<table class="form-table">
	<tr>
		<th><label
				for="company_name"><?php _e('Company Name', 'wp-remote-jobs'); ?></label>
		</th>
		<td>
			<input type="text" name="company_name" id="company_name"
				value="<?php echo esc_attr(get_user_meta($user->ID, 'company_name', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_hq"><?php _e('Company HQ', 'wp-remote-jobs'); ?></label>
		</th>
		<td>
			<input type="text" name="company_hq" id="company_hq"
				value="<?php echo esc_attr(get_user_meta($user->ID, 'company_hq', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_logo"><?php _e('Logo URL', 'wp-remote-jobs'); ?></label>
		</th>
		<td>
			<input type="url" name="company_logo" id="company_logo"
				value="<?php echo esc_url(get_user_meta($user->ID, 'company_logo', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_website"><?php _e('Company Website URL', 'wp-remote-jobs'); ?></label>
		</th>
		<td>
			<input type="url" name="company_website" id="company_website"
				value="<?php echo esc_url(get_user_meta($user->ID, 'company_website', true)); ?>"
				class="regular-text" />
		</td>
	</tr>
	<tr>
		<th><label
				for="company_description"><?php _e('Company Description', 'wp-remote-jobs'); ?></label>
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
function save_company_details_fields($user_id)
{
    if (!current_user_can('edit_user', $user_id)) {
        return false;
    }

    update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
    update_user_meta($user_id, 'company_hq', sanitize_text_field($_POST['company_hq']));
    update_user_meta($user_id, 'company_logo', esc_url_raw($_POST['company_logo']));
    update_user_meta($user_id, 'company_website', esc_url_raw($_POST['company_website']));
    update_user_meta($user_id, 'company_description', wp_kses_post($_POST['company_description']));
}

// Add hooks to display and save the custom fields
add_action('show_user_profile', 'add_company_details_fields');
add_action('edit_user_profile', 'add_company_details_fields');
add_action('personal_options_update', 'save_company_details_fields');
add_action('edit_user_profile_update', 'save_company_details_fields');

?>