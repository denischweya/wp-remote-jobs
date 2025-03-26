<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function remjobs_render_job_listings_block($attributes, $content, $block)
{
    // Default to grid layout if not specified
    $layout = isset($attributes['layout']) ? $attributes['layout'] : 'grid';
    $layout_class = 'job-listings-' . $layout;

    $jobs_per_page = 10; // Default number of jobs to display
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    ob_start();
    ?>
<div <?php echo wp_kses_post(get_block_wrapper_attributes(['class' => 'job-listings-container'])); ?>>
    <?php
        $args = array(
            'post_type' => 'jobs',
            'posts_per_page' => $jobs_per_page,
            'post_status' => 'publish',
            'paged' => $paged
        );

    $jobs = new WP_Query($args);
    $job_count = $jobs->found_posts;

    // Display total job count and view toggle
    if ($jobs->have_posts()) : ?>
    <div class="job-listings-header">
        <div class="job-count">
            <h2 class="job-count-title">Latest jobs</h2>
            <p class="job-count-stats">
                <?php echo esc_html($job_count); ?> jobs live -
                <?php echo esc_html(date('j F')); ?>
            </p>
        </div>
        <div class="view-toggle">
            <a href="#" class="view-all-link">View all jobs</a>
            <div class="layout-toggle">
                <button type="button"
                    class="toggle-grid <?php echo $layout === 'grid' ? 'active' : ''; ?>"
                    aria-label="Grid view" data-view="grid">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                        <rect x="3" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1"></rect>
                    </svg>
                </button>
                <button type="button"
                    class="toggle-list <?php echo $layout === 'list' ? 'active' : ''; ?>"
                    aria-label="List view" data-view="list">
                    <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="<?php echo esc_attr($layout_class); ?>">
        <?php
            while ($jobs->have_posts()) : $jobs->the_post();
                $job_id = get_the_ID();
                $company_name = get_post_meta($job_id, 'company_name', true);
                $job_type = get_post_meta($job_id, 'job_type', true) ?: 'Full Time';
                $job_location = get_post_meta($job_id, 'job_location', true) ?: 'Remote';
                $salary_range = get_post_meta($job_id, 'salary_range', true) ?: '$25 - $40';
                $application_link = get_post_meta($job_id, 'application_link', true);
                $days_left = 118; // This would be calculated based on the job expiry date

                // Get company logo if available, or use a default placeholder
                $company_logo = get_post_meta($job_id, 'company_logo', true);
                if (!empty($company_logo)) {
                    $logo_html = wp_get_attachment_image($company_logo, 'thumbnail', false, array('class' => 'company-logo'));
                } else {
                    // Generate a colored circle with initials if no logo
                    $colors = array('#4299e1', '#48bb78', '#f56565', '#ed8936', '#9f7aea');
                    $color_index = crc32($company_name) % count($colors);
                    $initials = substr($company_name, 0, 1);
                    $logo_html = '<div class="company-logo-placeholder" style="background-color: ' . esc_attr($colors[$color_index]) . '">' . esc_html($initials) . '</div>';
                }

                // Get categories
                $categories = wp_get_post_terms($job_id, 'remjobs_job_category');
                $category_name = !empty($categories) ? $categories[0]->name : 'Development & IT';

                if ($layout === 'grid') :
                    ?>
        <!-- Grid View Job Card -->
        <div class="job-card">
            <div class="job-card-header">
                <div class="company-logo-container">
                    <?php echo $logo_html; ?>
                </div>
                <h3 class="job-title">
                    <a
                        href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <div class="job-company">
                    by <span
                        class="company-name"><?php echo esc_html($company_name); ?></span>
                    in <span
                        class="job-category"><?php echo esc_html($category_name); ?></span>
                </div>
            </div>

            <div class="job-card-meta">
                <div class="job-meta-info">
                    <div class="job-type-badge">
                        <?php echo esc_html($job_type); ?>
                    </div>
                    <div class="job-location">
                        <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2"
                            fill="none">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <?php echo esc_html($job_location); ?>
                    </div>
                    <div class="job-salary">
                        <?php echo esc_html($salary_range); ?>
                    </div>
                </div>
            </div>

            <div class="job-card-footer">
                <span
                    class="days-left"><?php echo esc_html($days_left); ?>
                    days left to apply</span>
                <?php if (!empty($application_link)) : ?>
                <a href="<?php echo esc_url($application_link); ?>"
                    class="save-job-button" aria-label="Save job">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php else : ?>
        <!-- List View Job Card -->
        <div class="job-row">
            <div class="job-row-main">
                <div class="company-logo-container">
                    <?php echo $logo_html; ?>
                </div>
                <div class="job-row-content">
                    <h3 class="job-title">
                        <a
                            href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h3>
                    <div class="job-company">
                        by <span
                            class="company-name"><?php echo esc_html($company_name); ?></span>
                        in <span
                            class="job-category"><?php echo esc_html($category_name); ?></span>
                    </div>
                </div>
            </div>

            <div class="job-row-meta">
                <div class="job-type-badge">
                    <?php echo esc_html($job_type); ?>
                </div>
                <div class="job-location">
                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    <?php echo esc_html($job_location); ?>
                </div>
                <div class="job-salary">
                    <?php echo esc_html($salary_range); ?>
                </div>
            </div>

            <div class="job-row-actions">
                <span
                    class="days-left"><?php echo esc_html($days_left); ?>
                    days left to apply</span>
                <?php if (!empty($application_link)) : ?>
                <a href="<?php echo esc_url($application_link); ?>"
                    class="save-job-button" aria-label="Save job">
                    <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endwhile; ?>
    </div>

    <?php if ($jobs->max_num_pages > 1) : ?>
    <div class="job-pagination">
        <?php
        echo paginate_links(array(
            'base' => str_replace(999999999, '%#%', esc_url(get_pagenum_link(999999999))),
            'format' => '?paged=%#%',
            'current' => max(1, get_query_var('paged')),
            'total' => $jobs->max_num_pages,
            'prev_text' => '&laquo; Previous',
            'next_text' => 'Next &raquo;',
        ));
        ?>
    </div>
    <?php endif; ?>

    <?php else : ?>
    <div class="no-jobs-found">
        <p><?php esc_html_e('No jobs found.', 'remote-jobs'); ?>
        </p>
    </div>
    <?php
    endif;
    wp_reset_postdata();
    ?>
</div>
<?php
    return ob_get_clean();
}
?>