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
        // Process form submission
        $user_id = wp_create_user($_POST['email'], wp_generate_password(), $_POST['email']);

        if (!is_wp_error($user_id)) {
            update_user_meta($user_id, 'company_name', sanitize_text_field($_POST['company_name']));
            update_user_meta($user_id, 'company_hq', sanitize_text_field($_POST['company_hq']));
            update_user_meta($user_id, 'company_logo', esc_url_raw($_POST['logo']));
            update_user_meta($user_id, 'company_website', esc_url_raw($_POST['website_url']));
            update_user_meta($user_id, 'company_description', wp_kses_post($_POST['description']));

            // You might want to add a success message here
            echo '<p>' . __('Registration successful!', 'wp-remote-jobs') . '</p>';
        } else {
            // Handle registration error
            echo '<p>' . __('Registration failed. Please try again.', 'wp-remote-jobs') . '</p>';
        }
    }

    ob_start();
    ?>
<div class="wp-block-wp-remote-jobs-registration">
	<h2><?php esc_html_e('Registration Form', 'registration'); ?>
	</h2>
	<form id="registration-form" method="post">
		<div>
			<label
				for="company-name"><?php esc_html_e('Company Name', 'registration'); ?></label>
			<input type="text" id="company-name" name="company_name"
				value="<?php echo $company_name; ?>" required>
		</div>
		<div>
			<label
				for="company-hq"><?php esc_html_e('Company HQ', 'registration'); ?></label>
			<input type="text" id="company-hq" name="company_hq"
				value="<?php echo $company_hq; ?>" required>
		</div>
		<div>
			<label
				for="logo"><?php esc_html_e('Logo', 'registration'); ?></label>
			<input type="url" id="logo" name="logo"
				value="<?php echo $logo; ?>">
		</div>
		<div>
			<label
				for="website-url"><?php esc_html_e('Company Website URL', 'registration'); ?></label>
			<input type="url" id="website-url" name="website_url"
				value="<?php echo $website_url; ?>" required>
		</div>
		<div>
			<label
				for="email"><?php esc_html_e('Email', 'registration'); ?></label>
			<input type="email" id="email" name="email"
				value="<?php echo $email; ?>" required>
		</div>
		<div>
			<label
				for="description"><?php esc_html_e('Company Description', 'registration'); ?></label>
			<textarea id="description" name="description"
				required><?php echo $description; ?></textarea>
		</div>
		<div>
			<input type="submit"
				value="<?php esc_attr_e('Submit', 'registration'); ?>">
		</div>
	</form>
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