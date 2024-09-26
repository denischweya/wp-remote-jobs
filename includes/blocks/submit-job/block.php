<?php
function render_submit_job_block($attributes, $content)
{
    ob_start();
    ?>
<form id="job-submission-form" method="post">
    <input type="text" name="job_title" placeholder="Job Title" required>

    <select name="job_category" required>
        <option value="">Select Job Category</option>
        <?php
            $categories = get_terms(['taxonomy' => 'job_category', 'hide_empty' => false]);
    foreach ($categories as $category) {
        echo '<option value="' . esc_attr($category->term_id) . '">' . esc_html($category->name) . '</option>';
    }
    ?>
    </select>

    <select name="job_skills[]" multiple required class="select2-multi" placeholder="<?php esc_attr_e('Skills', 'wp-remote-jobs'); ?>">
        <?php
            $skills = get_terms(['taxonomy' => 'job_skills', 'hide_empty' => false]);
    foreach ($skills as $skill) {
        echo '<option value="' . esc_attr($skill->term_id) . '">' . esc_html($skill->name) . '</option>';
    }
    ?>
    </select>


    <fieldset>
        <legend>Is position open worldwide?</legend>
        <input type="radio" name="worldwide" value="yes" required> Yes
        <input type="radio" name="worldwide" value="no" required> No
    </fieldset>

    <select name="job_location" style="display:none;" class="select2-countries">
        <option value="">Select Country</option>
        <?php
        $countries = get_countries_list(); // You need to implement this function
    foreach ($countries as $country_code => $country_name) {
        echo '<option value="' . esc_attr($country_code) . '">' . esc_html($country_name) . '</option>';
    }
    ?>
    </select>

    <select name="employment_type" required>
        <option value="">Select Employment Type</option>
        <?php
    $employment_types = get_terms(['taxonomy' => 'employment_type', 'hide_empty' => false]);
    foreach ($employment_types as $type) {
        echo '<option value="' . esc_attr($type->term_id) . '">' . esc_html($type->name) . '</option>';
    }
    ?>
    </select>

    <select name="salary_range" required>
        <option value="">Select Salary Range</option>
        <option value="0-30000">$0 - $30,000</option>
        <option value="30001-60000">$30,001 - $60,000</option>
        <option value="60001-90000">$60,001 - $90,000</option>
        <option value="90001-120000">$90,001 - $120,000</option>
        <option value="120001+">$120,001+</option>
    </select>

    <textarea name="job_description" placeholder="Job Description" required></textarea>


    <select name="benefits[]" multiple class="select2-multi">
        <?php
            $benefits = get_terms(['taxonomy' => 'benefits', 'hide_empty' => false]);
    foreach ($benefits as $benefit) {
        echo '<option value="' . esc_attr($benefit->term_id) . '">' . esc_html($benefit->name) . '</option>';
    }
    ?>
    </select>

    <textarea name="how_to_apply" placeholder="How to Apply" required></textarea>

    <input type="submit" value="Submit Job">
    <?php wp_nonce_field('submit_job', 'job_nonce'); ?>
</form>
<script>
    document.querySelector('input[name="worldwide"]').addEventListener('change', function() {
        document.querySelector('select[name="job_location"]').style.display = this.value === 'no' ? 'block' :
            'none';
    });
</script>
<?php
    return ob_get_clean();
}

function handle_job_submission()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_nonce']) && wp_verify_nonce($_POST['job_nonce'], 'submit_job')) {
        $job_title = sanitize_text_field($_POST['job_title']);
        $job_description = wp_kses_post($_POST['job_description']);
        $worldwide = sanitize_text_field($_POST['worldwide']);
        $salary_range = sanitize_text_field($_POST['salary_range']);
        $how_to_apply = sanitize_textarea_field($_POST['how_to_apply']);

        $job_id = wp_insert_post(array(
            'post_title'   => $job_title,
            'post_content' => $job_description,
            'post_status'  => 'pending',
            'post_type'    => 'jobs',
        ));

        if ($job_id) {
            // Set taxonomies
            wp_set_object_terms($job_id, intval($_POST['job_category']), 'job_category');
            wp_set_object_terms($job_id, array_map('intval', $_POST['job_skills']), 'job_skills');
            wp_set_object_terms($job_id, intval($_POST['employment_type']), 'employment_type');
            wp_set_object_terms($job_id, array_map('intval', $_POST['benefits']), 'benefits');

            if ($worldwide === 'no') {
                $job_location = sanitize_text_field($_POST['job_location']);
                update_post_meta($job_id, '_job_location', $job_location);
            }

            // Set custom fields
            update_post_meta($job_id, '_worldwide', $worldwide);
            update_post_meta($job_id, '_salary_range', $salary_range);
            update_post_meta($job_id, '_how_to_apply', $how_to_apply);

            wp_redirect(add_query_arg('job_submitted', 'success', wp_get_referer()));
            exit;
        }
    }
}
add_action('template_redirect', 'handle_job_submission');
?>