<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Render the job sidebar block.
 *
 * @param array  $attributes Block attributes.
 * @param string $content    Block content.
 * @return string The rendered sidebar HTML.
 */
function remjobs_render_job_sidebar_block($attributes, $content)
{
    $job_id = get_the_ID();

    // Company Name
    $company_name = get_post_meta($job_id, '_remjobs_company_name', true) ?: get_post_meta($job_id, 'company_name', true);
    $company_name = !empty($company_name) ? $company_name : esc_html__('Company Name Not Provided', 'remote-jobs');

    // Salary Range
    $salary_range = get_post_meta($job_id, '_remjobs_salary', true) ?: get_post_meta($job_id, '_remjobs_salary_range', true);
    $salary_range = !empty($salary_range) ? $salary_range : esc_html__('Salary To Be Discussed', 'remote-jobs');

    // Location: prefer the free-text meta, fall back to the taxonomy term.
    $location = get_post_meta($job_id, '_remjobs_location', true);
    if (empty($location)) {
        $location_terms = get_the_terms($job_id, 'remjobs_job_location');
        if (!is_wp_error($location_terms) && !empty($location_terms)) {
            $location = $location_terms[0]->name;
        }
    }

    // Employment type: prefer meta, fall back to the taxonomy term.
    $employment_type = get_post_meta($job_id, '_remjobs_employment_type', true) ?: get_post_meta($job_id, '_remjobs_job_type', true);
    if (empty($employment_type)) {
        $employment_terms = get_the_terms($job_id, 'remjobs_job_employment_type');
        if (!is_wp_error($employment_terms) && !empty($employment_terms)) {
            $employment_type = $employment_terms[0]->name;
        }
    }

    // Application deadline, shown only while still in the future.
    $deadline_text = '';
    $expiry_date = get_post_meta($job_id, '_remjobs_expiry_date', true);
    if ($expiry_date) {
        $expiry_timestamp = strtotime($expiry_date);
        if ($expiry_timestamp && $expiry_timestamp > current_time('timestamp')) {
            $deadline_text = date_i18n(get_option('date_format'), $expiry_timestamp);
        }
    }

    // Required skills (taxonomy terms).
    $skills = array();
    $skill_terms = get_the_terms($job_id, 'remjobs_job_skills');
    if (!is_wp_error($skill_terms) && !empty($skill_terms)) {
        $skills = wp_list_pluck($skill_terms, 'name');
    }

    // Benefits (taxonomy terms).
    $benefits = array();
    $benefit_terms = get_the_terms($job_id, 'remjobs_job_benefits');
    if (!is_wp_error($benefit_terms) && !empty($benefit_terms)) {
        $benefits = wp_list_pluck($benefit_terms, 'name');
    }

    // Application Link
    $application_link = get_post_meta($job_id, '_remjobs_application_link', true);

    // Company Website
    $company_website = get_post_meta($job_id, '_remjobs_company_website', true) ?: get_post_meta($job_id, 'company_website', true);

    ob_start();
    ?>
<div <?php echo wp_kses_post(get_block_wrapper_attributes(['class' => 'job-sidebar'])); ?>>
    <div class="job-sidebar-content">
        <div class="job-sidebar-field job-company">
            <h4><?php esc_html_e('Company', 'remote-jobs'); ?></h4>
            <p><?php echo esc_html($company_name); ?></p>
        </div>

        <div class="job-sidebar-field job-salary">
            <h4><?php esc_html_e('Salary Range', 'remote-jobs'); ?></h4>
            <p><?php echo esc_html($salary_range); ?></p>
        </div>

        <?php if ($location) : ?>
        <div class="job-sidebar-field">
            <h4><?php esc_html_e('Location', 'remote-jobs'); ?></h4>
            <p><?php echo esc_html($location); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($employment_type) : ?>
        <div class="job-sidebar-field">
            <h4><?php esc_html_e('Employment Type', 'remote-jobs'); ?></h4>
            <p><?php echo esc_html($employment_type); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($deadline_text) : ?>
        <div class="job-sidebar-field">
            <h4><?php esc_html_e('Apply By', 'remote-jobs'); ?></h4>
            <p><?php echo esc_html($deadline_text); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($skills)) : ?>
        <div class="job-sidebar-field">
            <h4><?php esc_html_e('Skills', 'remote-jobs'); ?></h4>
            <div class="job-sidebar-tags">
                <?php foreach ($skills as $skill) : ?>
                <span class="skill-tag"><?php echo esc_html($skill); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($benefits)) : ?>
        <div class="job-sidebar-field">
            <h4><?php esc_html_e('Benefits', 'remote-jobs'); ?></h4>
            <ul class="job-sidebar-benefits">
                <?php foreach ($benefits as $benefit) : ?>
                <li><?php echo esc_html($benefit); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ($application_link) : ?>
        <div class="job-apply">
            <a href="<?php echo esc_url($application_link); ?>"
                class="apply-button" target="_blank" rel="noopener">
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

        <?php if ($company_website) : ?>
        <div class="company-website">
            <a href="<?php echo esc_url($company_website); ?>"
                target="_blank" rel="noopener">
                <?php esc_html_e('Visit Company Website', 'remote-jobs'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php
    return ob_get_clean();
}
