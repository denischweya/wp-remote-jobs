<?php
/**
 * Job List Block
 *
 * @package RemJobs
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register Job List Block
 */
function remjobs_register_job_list_block()
{
    if (! function_exists('register_block_type')) {
        return;
    }

    // Check if block type is already registered
    if (WP_Block_Type_Registry::get_instance()->is_registered('remjobs/job-list')) {
        return;
    }

    register_block_type(
        __DIR__ . '/build',
        array(
            'render_callback' => 'remjobs_render_job_list_block',
        )
    );
}
add_action('init', 'remjobs_register_job_list_block');

/**
 * Render the job list block
 *
 * @param array $attributes Block attributes.
 * @return string Block HTML.
 */
function remjobs_render_job_list_block($attributes)
{
    // Extract block attributes with defaults
    $block_title = !empty($attributes['blockTitle']) ? $attributes['blockTitle'] : __('Latest jobs', 'remote-jobs');
    $background_color = !empty($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '#f7f9fc';
    $layout = !empty($attributes['layout']) ? $attributes['layout'] : 'grid';
    $posts_per_page = !empty($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 10;
    $show_toggle = !empty($attributes['showToggle']) ? $attributes['showToggle'] : true;
    $show_filters = !empty($attributes['showFilters']) ? $attributes['showFilters'] : false;
    $featured_only = !empty($attributes['featuredOnly']) ? $attributes['featuredOnly'] : false;

    // Filter visibility settings
    $show_category_filter = !empty($attributes['showCategoryFilter']) ? $attributes['showCategoryFilter'] : true;
    $show_location_filter = !empty($attributes['showLocationFilter']) ? $attributes['showLocationFilter'] : true;
    $show_skills_filter = !empty($attributes['showSkillsFilter']) ? $attributes['showSkillsFilter'] : false;
    $show_search_filter = !empty($attributes['showSearchFilter']) ? $attributes['showSearchFilter'] : true;

    // Taxonomy filters
    $filter_by_category = !empty($attributes['filterByCategory']) ? $attributes['filterByCategory'] : array();
    $filter_by_skills = !empty($attributes['filterBySkills']) ? $attributes['filterBySkills'] : array();
    $filter_by_location = !empty($attributes['filterByLocation']) ? $attributes['filterByLocation'] : array();

    // Start output buffer
    ob_start();

    // Check if we have any jobs before proceeding
    $check_jobs = new WP_Query(array(
        'post_type' => 'remjobs_jobs',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ));

    $has_jobs = $check_jobs->have_posts();
    wp_reset_postdata();

    if (!$has_jobs) {
        echo '<div class="job-listings-container">';
        echo '<div class="no-jobs-found">';
        echo '<p>' . esc_html__('No jobs have been added yet.', 'remote-jobs') . '</p>';
        echo '</div>';
        echo '</div>';
        return ob_get_clean();
    }

    // Unique ID for this instance
    $block_id = 'job-list-' . wp_unique_id();

    // Initialize filter variables
    $url_category = '';
    $url_location = '';
    $url_skills = '';
    $url_search = '';

    // Only process GET parameters if they exist and verify nonce
    // This improves performance by not checking nonce on every page load
    if (!empty($_GET['category']) || !empty($_GET['location']) || !empty($_GET['skills']) || !empty($_GET['search'])) {
        // Get and verify nonce first
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        // Only proceed with filtering if nonce is valid
        if (wp_verify_nonce($nonce, 'job_filter_nonce')) {
            // Now safely sanitize and use the filter parameters
            $url_category = isset($_GET['category']) ? sanitize_text_field(wp_unslash($_GET['category'])) : '';
            $url_location = isset($_GET['location']) ? sanitize_text_field(wp_unslash($_GET['location'])) : '';
            $url_skills = isset($_GET['skills']) ? sanitize_text_field(wp_unslash($_GET['skills'])) : '';
            $url_search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        }
        // If nonce verification fails, filter parameters remain empty (safe default)
    }

    // Apply URL filters if they exist and passed nonce verification
    if (!empty($url_category)) {
        $filter_by_category = array($url_category);
    }

    if (!empty($url_location)) {
        $filter_by_location = array($url_location);
    }

    if (!empty($url_skills)) {
        $filter_by_skills = array($url_skills);
    }

    // Query args
    $paged = (get_query_var('paged')) ? get_query_var('paged') : 1;

    $args = array(
    'post_type'      => 'remjobs_jobs',
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    );

    // Add meta query for featured jobs if needed
    if ($featured_only) {
        $args['meta_query'] = array(
            array(
                'key'     => '_remjobs_is_premium',
                'value'   => '1',
                'compare' => '=',
            ),
        );
    }

    // Add search if provided
    if (!empty($url_search)) {
        $args['s'] = $url_search;
    }

    // Add taxonomy queries if filters are set
    $tax_query = array();

    if (! empty($filter_by_category)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_category',
            'field'    => 'slug',
            'terms'    => $filter_by_category,
        );
    }

    if (! empty($filter_by_skills)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_skills',
            'field'    => 'slug',
            'terms'    => $filter_by_skills,
        );
    }

    if (! empty($filter_by_location)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_location',
            'field'    => 'slug',
            'terms'    => $filter_by_location,
        );
    }

    if (! empty($tax_query)) {
        $tax_query['relation'] = 'AND';
        $args['tax_query'] = $tax_query;
    }

    // Run the query
    $jobs_query = new WP_Query($args);

    // Get total job count for display
    $job_count_args = array(
        'post_type'      => 'remjobs_jobs',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $total_jobs = new WP_Query($job_count_args);
    $total_jobs_count = $total_jobs->found_posts;

    // Get today's job count
    $today = getdate();
    $today_job_args = array(
        'post_type'      => 'remjobs_jobs',
        'post_status'    => 'publish',
            'posts_per_page' => -1,
        'fields'         => 'ids',
        'date_query'     => array(
            array(
                'year'  => $today['year'],
                'month' => $today['mon'],
                'day'   => $today['mday'],
            ),
        ),
    );
    $today_jobs = new WP_Query($today_job_args);
    $today_jobs_count = $today_jobs->found_posts;

    // Block container with data attributes
    $container_class = 'job-listings-container';
    $container_style = sprintf('style="background-color: %s;"', esc_attr($background_color));
    $container_attrs = sprintf(
        'data-layout="%s" id="%s" %s',
        esc_attr($layout),
        esc_attr($block_id),
        $container_style
    );

    echo '<div class="' . esc_attr($container_class) . '" ' . wp_kses_post($container_attrs) . '>';

    // Display the header with job count and layout toggle
    echo '<div class="job-listings-header">';

    // Job count display
    echo '<div class="job-count">';
    echo '<h2 class="job-count-title">' . esc_html($block_title) . '</h2>';

    // Format job counts
    echo '<p class="job-count-stats">';

    /* translators: %d: Number of job listings */
    $jobs_live_text = _n(
        '%d job live',
        '%d jobs live',
        $total_jobs_count,
        'remote-jobs'
    );
    echo esc_html(sprintf($jobs_live_text, $total_jobs_count));

    if ($today_jobs_count > 0) {
        echo ' - ';
        /* translators: %d: Number of job listings added today */
        $added_today_text = _n(
            '%d added today',
            '%d added today',
            $today_jobs_count,
            'remote-jobs'
        );
        echo esc_html(sprintf($added_today_text, $today_jobs_count));
    }

    echo '</p>';
    echo '</div>'; // End job count

    // Layout toggle buttons
    if ($show_toggle) {
        echo '<div class="view-toggle">';

        // View all jobs link
        echo '<a href="' . esc_url(get_post_type_archive_link('remjobs_jobs')) . '" class="view-all-link">';
        echo esc_html__('View all jobs', 'remote-jobs');
        echo '</a>';

        // Toggle buttons
        echo '<div class="layout-toggle">';

        // Grid view button
        $grid_class = $layout === 'grid' ? 'active' : '';
        echo '<button type="button" class="layout-button grid-button ' . esc_attr($grid_class) . '" data-layout="grid" aria-label="' . esc_attr__('Grid view', 'remote-jobs') . '">';
        echo '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">';
        echo '<rect x="3" y="3" width="7" height="7" rx="1"></rect>';
        echo '<rect x="14" y="3" width="7" height="7" rx="1"></rect>';
        echo '<rect x="3" y="14" width="7" height="7" rx="1"></rect>';
        echo '<rect x="14" y="14" width="7" height="7" rx="1"></rect>';
        echo '</svg>';
        echo '</button>';

        // List view button
        $list_class = $layout === 'list' ? 'active' : '';
        echo '<button type="button" class="layout-button list-button ' . esc_attr($list_class) . '" data-layout="list" aria-label="' . esc_attr__('List view', 'remote-jobs') . '">';
        echo '<svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none">';
        echo '<line x1="8" y1="6" x2="21" y2="6"></line>';
        echo '<line x1="8" y1="12" x2="21" y2="12"></line>';
        echo '<line x1="8" y1="18" x2="21" y2="18"></line>';
        echo '<line x1="3" y1="6" x2="3.01" y2="6"></line>';
        echo '<line x1="3" y1="12" x2="3.01" y2="12"></line>';
        echo '<line x1="3" y1="18" x2="3.01" y2="18"></line>';
        echo '</svg>';
        echo '</button>';

        echo '</div>'; // End layout toggle
        echo '</div>'; // End view toggle
    }

    echo '</div>'; // End job listings header

    // Display filter controls if enabled
    if ($show_filters) {
        // Get available taxonomies for filters
        $categories = get_terms(array(
            'taxonomy'   => 'remjobs_job_category',
            'hide_empty' => true,
        ));

        $locations = get_terms(array(
            'taxonomy'   => 'remjobs_job_location',
            'hide_empty' => true,
        ));

        $skills = get_terms(array(
            'taxonomy'   => 'remjobs_job_skills',
            'hide_empty' => true,
        ));

        echo '<div class="job-filters">';
        echo '<div class="filter-row">';

        // Search input
        if ($show_search_filter) {
            $search_value = !empty($url_search) ? ' value="' . esc_attr($url_search) . '"' : '';
            echo '<input type="text" class="search-input" id="job-search" placeholder="' . esc_attr__('Search jobs...', 'remote-jobs') . '"' . $search_value . '>';
        }

        // Category dropdown
        if ($show_category_filter && !is_wp_error($categories) && !empty($categories)) {
            echo '<select class="category-select" id="job-category">';
            echo '<option value="">' . esc_html__('All Categories', 'remote-jobs') . '</option>';

            foreach ($categories as $category) {
                $selected = ($url_category === $category->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($category->slug) . '"' . $selected . '>' . esc_html($category->name) . '</option>';
            }

            echo '</select>';
        }

        // Location dropdown
        if ($show_location_filter && !is_wp_error($locations) && !empty($locations)) {
            echo '<select class="location-select" id="job-location">';
            echo '<option value="">' . esc_html__('All Locations', 'remote-jobs') . '</option>';

            foreach ($locations as $location) {
                $selected = ($url_location === $location->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($location->slug) . '"' . $selected . '>' . esc_html($location->name) . '</option>';
            }

            echo '</select>';
        }

        // Skills dropdown
        if ($show_skills_filter && !is_wp_error($skills) && !empty($skills)) {
            echo '<select class="skills-select" id="job-skills">';
            echo '<option value="">' . esc_html__('All Skills', 'remote-jobs') . '</option>';

            foreach ($skills as $skill) {
                $selected = ($url_skills === $skill->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($skill->slug) . '"' . $selected . '>' . esc_html($skill->name) . '</option>';
            }

            echo '</select>';
        }

        // Filter button
        echo '<button type="button" class="filter-button" id="filter-jobs">';
        echo esc_html__('Filter', 'remote-jobs');
        echo '</button>';

        echo '</div>'; // End filter row
        echo '</div>'; // End job filters
    }

    // Add container for AJAX updates
    echo '<div id="jobs-list">';

    // Start job listings with layout class
    echo '<div class="job-listings-' . esc_attr($layout) . '">';

    if ($jobs_query->have_posts()) {
        while ($jobs_query->have_posts()) {
            $jobs_query->the_post();

            // Get job meta data
            $company_name = get_post_meta(get_the_ID(), '_remjobs_company_name', true);
            $job_type     = get_post_meta(get_the_ID(), '_remjobs_job_type', true);
            $salary       = get_post_meta(get_the_ID(), '_remjobs_salary', true);
            $application_url = get_post_meta(get_the_ID(), '_remjobs_application_url', true);
            $is_premium   = get_post_meta(get_the_ID(), '_remjobs_is_premium', true) === '1';

            // Get job expiry
            $expiry_date = get_post_meta(get_the_ID(), '_remjobs_expiry_date', true);
            $days_left = '';

            if ($expiry_date) {
                $now = new DateTime();
                $expiry = new DateTime($expiry_date);
                if ($expiry > $now) {
                    $interval = $now->diff($expiry);
                    $days_left = $interval->days;
                } else {
                    $days_left = 0;
                }
            }

            // Get categories/taxonomies
            $job_categories = get_the_terms(get_the_ID(), 'remjobs_job_category');
            $job_locations = get_the_terms(get_the_ID(), 'remjobs_job_location');
            $job_skills = get_the_terms(get_the_ID(), 'remjobs_job_skills');

            $category_name = '';
            if (! is_wp_error($job_categories) && ! empty($job_categories)) {
                $category_name = $job_categories[0]->name;
            }

            $location_name = '';
            if (! is_wp_error($job_locations) && ! empty($job_locations)) {
                $location_name = $job_locations[0]->name;
            }

            $skills_list = array();
            if (! is_wp_error($job_skills) && ! empty($job_skills)) {
                foreach ($job_skills as $skill) {
                    $skills_list[] = $skill->name;
                }
            }

            // Set CSS class for premium jobs
            $premium_class = $is_premium ? 'premium' : '';

            if ($layout === 'grid') {
                // Grid layout - card style
                ?>
<div class="job-card <?php echo esc_attr($premium_class); ?>">
    <div class="job-card-header">
        <div class="company-logo-container">
            <?php if (has_post_thumbnail()) : ?>
            <div class="company-logo">
                <?php the_post_thumbnail('thumbnail'); ?>
            </div>
            <?php else : ?>
            <div class="company-logo-placeholder">
                <?php echo esc_html(isset($company_name[0]) ? $company_name[0] : '?'); ?>
            </div>
            <?php endif; ?>
        </div>

        <h3 class="job-title">
            <a
                href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <div class="job-company">
            <?php if ($company_name) : ?>
            <?php esc_html_e('by', 'remote-jobs'); ?>
            <span
                class="company-name"><?php echo esc_html($company_name); ?></span>
            <?php endif; ?>

            <?php if ($category_name) : ?>
            <?php esc_html_e('in', 'remote-jobs'); ?>
            <span
                class="job-category"><?php echo esc_html($category_name); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="job-card-meta">
        <div class="job-meta-info">
            <?php if ($job_type) : ?>
            <div class="job-type-badge">
                <?php echo esc_html($job_type); ?>
            </div>
            <?php endif; ?>

            <?php if ($location_name) : ?>
            <div class="job-location">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <?php echo esc_html($location_name); ?>
            </div>
            <?php endif; ?>

            <?php if ($salary) : ?>
            <div class="job-salary">
                <?php echo esc_html($salary); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($skills_list)) : ?>
        <div class="job-skills">
            <div class="skills-label">
                <?php esc_html_e('Skills:', 'remote-jobs'); ?>
            </div>
            <div class="skills-list">
                <?php echo esc_html(implode(', ', $skills_list)); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="job-card-footer">
        <?php if ($days_left !== '') : ?>
        <span class="days-left">
            <?php
                                if ($days_left > 0) {
                                    /* translators: %d: Number of days left to apply for the job */
                                    $days_left_text = _n(
                                        '%d day left to apply',
                                        '%d days left to apply',
                                        $days_left,
                                        'remote-jobs'
                                    );
                                    echo esc_html(sprintf($days_left_text, $days_left));
                                } else {
                                    esc_html_e('Closing today', 'remote-jobs');
                                }
                ?>
        </span>
        <?php endif; ?>

        <a href="#" class="save-job-button"
            data-job-id="<?php echo esc_attr(get_the_ID()); ?>"
            aria-label="<?php esc_attr_e('Save job', 'remote-jobs'); ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
        </a>
    </div>
</div>
<?php
            } else {
                // List layout - row style
                ?>
<div class="job-row <?php echo esc_attr($premium_class); ?>">
    <div class="job-row-main">
        <div class="company-logo-container">
            <?php if (has_post_thumbnail()) : ?>
            <div class="company-logo">
                <?php the_post_thumbnail('thumbnail'); ?>
            </div>
            <?php else : ?>
            <div class="company-logo-placeholder">
                <?php echo esc_html(isset($company_name[0]) ? $company_name[0] : '?'); ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="job-row-content">
            <h3 class="job-title">
                <a
                    href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>

            <div class="job-company">
                <?php if ($company_name) : ?>
                <?php esc_html_e('by', 'remote-jobs'); ?>
                <span
                    class="company-name"><?php echo esc_html($company_name); ?></span>
                <?php endif; ?>

                <?php if ($category_name) : ?>
                <?php esc_html_e('in', 'remote-jobs'); ?>
                <span
                    class="job-category"><?php echo esc_html($category_name); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($skills_list)) : ?>
            <div class="job-skills">
                <span class="skills-label">
                    <?php esc_html_e('Skills:', 'remote-jobs'); ?>
                </span>
                <span class="skills-list">
                    <?php echo esc_html(implode(', ', $skills_list)); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="job-row-meta">
        <?php if ($job_type) : ?>
        <div class="job-type-badge">
            <?php echo esc_html($job_type); ?>
        </div>
        <?php endif; ?>

        <?php if ($location_name) : ?>
        <div class="job-location">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                <circle cx="12" cy="10" r="3"></circle>
            </svg>
            <?php echo esc_html($location_name); ?>
        </div>
        <?php endif; ?>

        <?php if ($salary) : ?>
        <div class="job-salary"><?php echo esc_html($salary); ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="job-row-actions">
        <?php if ($days_left !== '') : ?>
        <span class="days-left">
            <?php
                                if ($days_left > 0) {
                                    /* translators: %d: Number of days left to apply for the job */
                                    $days_left_text = _n(
                                        '%d day left to apply',
                                        '%d days left to apply',
                                        $days_left,
                                        'remote-jobs'
                                    );
                                    echo esc_html(sprintf($days_left_text, $days_left));
                                } else {
                                    esc_html_e('Closing today', 'remote-jobs');
                                }
                ?>
        </span>
        <?php endif; ?>

        <a href="#" class="save-job-button"
            data-job-id="<?php echo esc_attr(get_the_ID()); ?>"
            aria-label="<?php esc_attr_e('Save job', 'remote-jobs'); ?>">
            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
            </svg>
        </a>
    </div>
</div>
<?php
            }
        }

        // Reset post data
        wp_reset_postdata();

        // Pagination
        $total_pages = $jobs_query->max_num_pages;

        if ($total_pages > 1) {
            echo '<div class="job-listings-pagination">';

            $current_page = max(1, get_query_var('paged'));

            echo wp_kses_post(paginate_links(array(
                'base'      => get_pagenum_link(1) . '%_%',
                'format'    => '?paged=%#%',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => '&laquo; ' . __('Previous', 'remote-jobs'),
                'next_text' => __('Next', 'remote-jobs') . ' &raquo;',
            )));

            echo '</div>';
        }
    } else {
        // No jobs found matching criteria (different from no jobs at all)
        echo '<div class="no-jobs-found">';
        echo '<p>' . esc_html__('No jobs found matching your criteria.', 'remote-jobs') . '</p>';
        echo '</div>';
    }

    echo '</div>'; // End job listings
    echo '</div>'; // End jobs-list (AJAX container)
    echo '</div>'; // End container

    return ob_get_clean();
}

// ============================================
// AJAX HANDLERS AND SCRIPT ENQUEUING
// ============================================

function remjobs_enqueue_job_filter()
{
    // WordPress auto-generates script handles for block viewScript
    // Common patterns: {namespace}-{block-name}-view-script
    $possible_handles = array(
        'remjobs-job-list-view-script',
        'remjobs-job-list-view',
        'wp-block-remjobs-job-list-view-script',
        'wp-block-remjobs-job-list',
        'remjobs-job-list'
    );

    $script_handle = null;

    // Find which script handle actually exists
    foreach ($possible_handles as $handle) {
        if (wp_script_is($handle, 'registered')) {
            $script_handle = $handle;
            break;
        }
    }

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        global $wp_scripts;
        $all_handles = array_keys($wp_scripts->registered);

        // Log ALL script handles to see what WordPress is actually registering
        error_log('=== ALL REGISTERED SCRIPT HANDLES ===');
        error_log(print_r($all_handles, true));

        $job_list_handles = array_filter($all_handles, function ($handle) {
            return strpos($handle, 'job-list') !== false || strpos($handle, 'remjobs') !== false || strpos($handle, 'view') !== false;
        });
        error_log('Handles containing job-list, remjobs, or view: ' . print_r($job_list_handles, true));

        if ($script_handle) {
            error_log('Found matching script handle: ' . $script_handle);
        } else {
            error_log('No matching script handle found. Trying manual enqueue.');
        }
    }

    // If we found a registered script, localize it
    if ($script_handle) {
        wp_localize_script($script_handle, 'remjobsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('filter_jobs_nonce')
        ));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Successfully localized script handle: ' . $script_handle);
        }
    } else {
        // Fallback: manually enqueue if auto-script not found or if block is present
        if (has_block('remjobs/job-list')) {
            wp_enqueue_script(
                'remjobs-job-list-manual',
                plugin_dir_url(__FILE__) . 'build/view.js',
                array('jquery'),
                '1.0.2',
                true
            );

            wp_localize_script('remjobs-job-list-manual', 'remjobsAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('filter_jobs_nonce')
            ));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Manually enqueued and localized remjobs-job-list-manual script');
            }
        }
    }
}
add_action('wp_enqueue_scripts', 'remjobs_enqueue_job_filter', 20);

// Alternative approach: Hook into block rendering to ensure localization
function remjobs_localize_on_block_render($block_content, $block)
{
    // Only target our specific block
    if (isset($block['blockName']) && $block['blockName'] === 'remjobs/job-list') {

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('=== Job List Block is being rendered ===');
        }

        // Try to localize any script handle that might be our view script
        global $wp_scripts;
        $all_handles = array_keys($wp_scripts->registered);

        // Look for handles that might be our view script
        $view_handles = array_filter($all_handles, function ($handle) {
            return (strpos($handle, 'remjobs') !== false && strpos($handle, 'view') !== false) ||
                   (strpos($handle, 'job-list') !== false && strpos($handle, 'view') !== false) ||
                   strpos($handle, 'remjobs-job-list') !== false;
        });

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Found potential view script handles: ' . print_r($view_handles, true));
        }

        // Try to localize all potential handles
        foreach ($view_handles as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_localize_script($handle, 'remjobsAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('filter_jobs_nonce')
                ));

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('Successfully localized handle during block render: ' . $handle);
                }
            }
        }

        // Also try the manual approach as backup
        static $manual_enqueued = false;
        if (!$manual_enqueued) {
            wp_enqueue_script(
                'remjobs-job-list-render',
                plugin_dir_url(__FILE__) . 'build/view.js',
                array('jquery'),
                '1.0.3',
                true
            );

            wp_localize_script('remjobs-job-list-render', 'remjobsAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('filter_jobs_nonce')
            ));

            $manual_enqueued = true;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('Manually enqueued script during block render');
            }
        }
    }

    return $block_content;
}
add_filter('render_block', 'remjobs_localize_on_block_render', 10, 2);

// Additional hook to catch script handles that are registered later
function remjobs_localize_scripts_later()
{
    // Run the same localization logic again in case scripts weren't ready before
    remjobs_enqueue_job_filter();
}
add_action('wp_footer', 'remjobs_localize_scripts_later', 5);

function remjobs_filter_jobs()
{
    // Add debugging at the start
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('=== remjobs_filter_jobs AJAX handler called ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('Request method: ' . $_SERVER['REQUEST_METHOD']);
        error_log('Content type: ' . (isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'not set'));
    }

    // Verify nonce (with debugging-friendly fallback)
    $nonce_valid = false;
    $nonce_provided = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!empty($nonce_provided)) {
        $nonce_valid = wp_verify_nonce($nonce_provided, 'filter_jobs_nonce');
    }

    // In debug mode, be more permissive to help with testing
    $allow_without_nonce = defined('WP_DEBUG') && WP_DEBUG;

    if (!$nonce_valid && !$allow_without_nonce) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Nonce verification failed');
            error_log('Received nonce: ' . $nonce_provided);
            error_log('Expected nonce action: filter_jobs_nonce');
        }
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        if ($nonce_valid) {
            error_log('Nonce verification passed');
        } else {
            error_log('Proceeding without nonce validation (debug mode)');
        }
    }

    // Sanitize inputs
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $skills = isset($_POST['skills']) ? sanitize_text_field(wp_unslash($_POST['skills'])) : '';
    $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
    $layout = isset($_POST['layout']) ? sanitize_text_field(wp_unslash($_POST['layout'])) : 'grid';

    // Debug logging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('AJAX Filter Request - Search: ' . $search . ', Category: ' . $category . ', Skills: ' . $skills . ', Location: ' . $location . ', Layout: ' . $layout);
    }

    // Build query args
    $args = array(
        'post_type' => 'remjobs_jobs', // Use the prefixed post type
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );

    // Add search if provided
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Build taxonomy query with prefixed taxonomy names
    $tax_query = array();

    if (!empty($category)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_category', // Use prefixed taxonomy
            'field' => 'slug',
            'terms' => explode(',', $category),
            'operator' => 'IN'
        );
    }

    if (!empty($skills)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_skills', // Use prefixed taxonomy
            'field' => 'slug',
            'terms' => explode(',', $skills),
            'operator' => 'IN'
        );
    }

    if (!empty($location)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_location', // Use prefixed taxonomy
            'field' => 'slug',
            'terms' => explode(',', $location),
            'operator' => 'IN'
        );
    }

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }

    // Debug the query
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Query args: ' . print_r($args, true));
    }

    // Execute query
    $query = new WP_Query($args);

    ob_start();

    // Start job listings with layout class
    echo '<div class="job-listings-' . esc_attr($layout) . '">';

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            // Get post meta
            $company = get_post_meta(get_the_ID(), '_remjobs_company', true);
            $location_meta = get_post_meta(get_the_ID(), '_remjobs_location', true);
            $salary = get_post_meta(get_the_ID(), '_remjobs_salary', true);
            $employment_type = get_post_meta(get_the_ID(), '_remjobs_employment_type', true);
            $featured = get_post_meta(get_the_ID(), '_remjobs_featured', true);

            // Get taxonomies with prefixed names
            $categories = get_the_terms(get_the_ID(), 'remjobs_job_category');
            $skills = get_the_terms(get_the_ID(), 'remjobs_job_skills');
            $locations = get_the_terms(get_the_ID(), 'remjobs_job_location');

            $premium_class = $featured ? ' premium' : '';

            if ($layout === 'grid') {
                // Grid layout
                echo '<div class="job-card' . esc_attr($premium_class) . '">';

                echo '<div class="job-card-header">';
                echo '<h3 class="job-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
                if ($company) {
                    echo '<p class="company-name">' . esc_html($company) . '</p>';
                }
                echo '</div>';

                echo '<div class="job-card-body">';
                if ($location_meta || $locations) {
                    echo '<div class="job-location">';
                    echo '<span class="location-icon">📍</span>';
                    if ($location_meta) {
                        echo '<span>' . esc_html($location_meta) . '</span>';
                    }
                    if ($locations && !is_wp_error($locations)) {
                        $location_names = array_map(function ($term) {
                            return $term->name;
                        }, $locations);
                        echo '<span>' . esc_html(implode(', ', $location_names)) . '</span>';
                    }
                    echo '</div>';
                }

                if ($employment_type) {
                    echo '<div class="employment-type">';
                    echo '<span class="type-badge">' . esc_html($employment_type) . '</span>';
                    echo '</div>';
                }

                if ($salary) {
                    echo '<div class="salary">';
                    echo '<span class="salary-icon">💰</span>';
                    echo '<span>' . esc_html($salary) . '</span>';
                    echo '</div>';
                }

                // Skills
                if ($skills && !is_wp_error($skills)) {
                    $skills_list = array_map(function ($term) {
                        return $term->name;
                    }, array_slice($skills, 0, 3)); // Show max 3 skills

                    echo '<div class="job-skills">';
                    echo '<span class="skills-label">' . esc_html__('Skills:', 'remote-jobs') . '</span> ';
                    echo '<span class="skills-list">' . esc_html(implode(', ', $skills_list)) . '</span>';
                    echo '</div>';
                }

                echo '</div>';

                echo '<div class="job-card-footer">';
                echo '<span class="job-date">' . esc_html(get_the_date()) . '</span>';
                echo '<button class="save-job-button" data-job-id="' . esc_attr(get_the_ID()) . '" aria-label="' . esc_attr__('Save job', 'remote-jobs') . '">';
                echo '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">';
                echo '<path d="M19 21L12 16L5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                echo '</svg>';
                echo '</button>';
                echo '</div>';

                echo '</div>';
            } else {
                // List layout
                echo '<div class="job-row' . esc_attr($premium_class) . '">';

                echo '<div class="job-row-content">';
                echo '<div class="job-row-main">';
                echo '<h3 class="job-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
                if ($company) {
                    echo '<p class="company-name">' . esc_html($company) . '</p>';
                }
                echo '</div>';

                echo '<div class="job-row-meta">';
                if ($location_meta || $locations) {
                    echo '<span class="job-location">';
                    echo '<span class="location-icon">📍</span>';
                    if ($location_meta) {
                        echo esc_html($location_meta);
                    }
                    if ($locations && !is_wp_error($locations)) {
                        $location_names = array_map(function ($term) {
                            return $term->name;
                        }, $locations);
                        echo esc_html(implode(', ', $location_names));
                    }
                    echo '</span>';
                }

                if ($employment_type) {
                    echo '<span class="employment-type">' . esc_html($employment_type) . '</span>';
                }

                if ($salary) {
                    echo '<span class="salary">💰 ' . esc_html($salary) . '</span>';
                }
                echo '</div>';

                echo '<div class="job-row-actions">';
                echo '<span class="job-date">' . esc_html(get_the_date()) . '</span>';
                echo '<button class="save-job-button" data-job-id="' . esc_attr(get_the_ID()) . '" aria-label="' . esc_attr__('Save job', 'remote-jobs') . '">';
                echo '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">';
                echo '<path d="M19 21L12 16L5 21V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                echo '</svg>';
                echo '</button>';
                echo '</div>';

                echo '</div>';
                echo '</div>';
            }
        }
    } else {
        echo '<div class="no-jobs-found">';

        // Build a more descriptive no results message
        $filter_descriptions = array();

        if (!empty($search)) {
            $filter_descriptions[] = 'search term "' . esc_html($search) . '"';
        }

        if (!empty($category)) {
            $filter_descriptions[] = 'category "' . esc_html($category) . '"';
        }

        if (!empty($location)) {
            $filter_descriptions[] = 'location "' . esc_html($location) . '"';
        }

        if (!empty($skills)) {
            $filter_descriptions[] = 'skills "' . esc_html($skills) . '"';
        }

        if (!empty($filter_descriptions)) {
            $message = sprintf(
                esc_html__('No jobs found matching your filters: %s. Try adjusting your search criteria.', 'remote-jobs'),
                implode(', ', $filter_descriptions)
            );
        } else {
            $message = esc_html__('No jobs found. Please try different search criteria.', 'remote-jobs');
        }

        echo '<p>' . $message . '</p>';
        echo '</div>';

        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('No jobs found. Query returned ' . $query->found_posts . ' posts. SQL: ' . $query->request);
        }
    }

    echo '</div>'; // End job listings

    wp_reset_postdata();
    $output = ob_get_clean();

    // Debug the final output
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Query completed. Found posts: ' . $query->found_posts);
        error_log('Output length: ' . strlen($output));
        error_log('Output preview (first 200 chars): ' . substr($output, 0, 200));
    }

    wp_send_json_success(array(
        'data' => $output,
        'found' => $query->found_posts
    ));
}

add_action('wp_ajax_filter_jobs', 'remjobs_filter_jobs');
add_action('wp_ajax_nopriv_filter_jobs', 'remjobs_filter_jobs');

// Additional AJAX endpoint to provide nonces
function remjobs_get_nonce()
{
    wp_send_json_success(array(
        'nonce' => wp_create_nonce('filter_jobs_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_ajax_get_filter_nonce', 'remjobs_get_nonce');
add_action('wp_ajax_nopriv_get_filter_nonce', 'remjobs_get_nonce');
?>