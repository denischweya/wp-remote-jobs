<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function render_submit_job_block($attributes, $content, $block) {
    ob_start();
    ?>
    <div <?php echo get_block_wrapper_attributes(['class' => 'submit-job-form']); ?>>
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

function enqueue_submit_job_assets() {
    $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');
    
    wp_enqueue_style(
        'submit-job-style',
        plugins_url('build/style-index.css', __FILE__),
        [],
        $asset_file['version']
    );
}
add_action('init', 'enqueue_submit_job_assets');