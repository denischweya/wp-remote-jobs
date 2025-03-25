<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function render_job_sidebar_block($attributes, $content)
{
    $job_id = get_the_ID();

    ob_start();
    ?>
<div <?php echo get_block_wrapper_attributes(['class' => 'job-sidebar']); ?>>
    <div class="job-sidebar-content">
        <?php
            // Company Name
            $company_name = get_post_meta($job_id, 'company_name', true);
    $company_name = !empty($company_name) ? $company_name : esc_html__('Company Name Not Provided', 'remote-jobs');
    ?>
        <div class="job-company">
            <h4><?php esc_html_e('Company', 'remote-jobs'); ?>
            </h4>
            <p><?php echo esc_html($company_name); ?></p>
        </div>

        <?php
    // Salary Range
    $salary_range = get_post_meta($job_id, 'salary_range', true);
    $salary_range = !empty($salary_range) ? $salary_range : esc_html__('Salary To Be Discussed', 'remote-jobs');
    ?>
        <div class="job-salary">
            <h4><?php esc_html_e('Salary Range', 'remote-jobs'); ?>
            </h4>
            <p><?php echo esc_html($salary_range); ?></p>
        </div>

        <?php
    // Application Link
    $application_link = get_post_meta($job_id, 'application_link', true);
    if ($application_link) : ?>
        <div class="job-apply">
            <a href="<?php echo esc_url($application_link); ?>"
                class="apply-button" target="_blank">
                <?php esc_html_e('Apply Now', 'remote-jobs'); ?>
            </a>
        </div>
        <?php else : ?>
        <div class="job-apply">
            <p class="no-link-message">
                <?php esc_html_e('Application link not available yet', 'remote-jobs'); ?>
            </p>
        </div>
        <?php endif; ?>

        <?php
    // Company Website
    $company_website = get_post_meta($job_id, 'company_website', true);
    if ($company_website) : ?>
        <div class="company-website">
            <a href="<?php echo esc_url($company_website); ?>"
                target="_blank">
                <?php esc_html_e('Visit Company Website', 'remote-jobs'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
    return ob_get_clean();
}
