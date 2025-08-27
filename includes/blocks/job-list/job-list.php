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
            echo '<input type="text" class="search-input" id="job-search" placeholder="' . esc_attr__('Search jobs...', 'remote-jobs') . '"' . esc_attr($search_value) . '>';
        }

        // Category dropdown
        if ($show_category_filter && !is_wp_error($categories) && !empty($categories)) {
            echo '<select class="category-select" id="job-category">';
            echo '<option value="">' . esc_html__('All Categories', 'remote-jobs') . '</option>';

            foreach ($categories as $category) {
                $selected = ($url_category === $category->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($category->slug) . '"' . esc_attr($selected) . '>' . esc_html($category->name) . '</option>';
            }

            echo '</select>';
        }

        // Location dropdown
        if ($show_location_filter && !is_wp_error($locations) && !empty($locations)) {
            echo '<select class="location-select" id="job-location">';
            echo '<option value="">' . esc_html__('All Locations', 'remote-jobs') . '</option>';

            foreach ($locations as $location) {
                $selected = ($url_location === $location->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($location->slug) . '"' . esc_attr($selected) . '>' . esc_html($location->name) . '</option>';
            }

            echo '</select>';
        }

        // Skills dropdown
        if ($show_skills_filter && !is_wp_error($skills) && !empty($skills)) {
            echo '<select class="skills-select" id="job-skills">';
            echo '<option value="">' . esc_html__('All Skills', 'remote-jobs') . '</option>';

            foreach ($skills as $skill) {
                $selected = ($url_skills === $skill->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($skill->slug) . '"' . esc_attr($selected) . '>' . esc_html($skill->name) . '</option>';
            }

            echo '</select>';
        }

        // Clear filters link
        echo '<a href="#" class="clear-filters-link" id="clear-filters">';
        echo esc_html__('Clear filters', 'remote-jobs');
        echo '</a>';

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
            $company = get_post_meta(get_the_ID(), '_remjobs_company', true);
            $location_meta = get_post_meta(get_the_ID(), '_remjobs_location', true);
            $job_type     = get_post_meta(get_the_ID(), '_remjobs_job_type', true);
            $employment_type = get_post_meta(get_the_ID(), '_remjobs_employment_type', true);
            $salary       = get_post_meta(get_the_ID(), '_remjobs_salary', true);
            $application_url = get_post_meta(get_the_ID(), '_remjobs_application_url', true);
            $is_premium   = get_post_meta(get_the_ID(), '_remjobs_is_premium', true) === '1';
            $featured = get_post_meta(get_the_ID(), '_remjobs_featured', true);

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
            $categories = get_the_terms(get_the_ID(), 'remjobs_job_category');
            $locations = get_the_terms(get_the_ID(), 'remjobs_job_location');
            $skills = get_the_terms(get_the_ID(), 'remjobs_job_skills');

            $category_name = '';
            if (! is_wp_error($categories) && ! empty($categories)) {
                $category_name = $categories[0]->name;
            }

            $location_name = '';
            if (! is_wp_error($locations) && ! empty($locations)) {
                $location_name = $locations[0]->name;
            }

            $skills_list = array();
            if (! is_wp_error($skills) && ! empty($skills)) {
                foreach ($skills as $skill) {
                    $skills_list[] = $skill->name;
                }
            }

            // Set CSS class for premium jobs (consistent with AJAX handler)
            $premium_class = $featured ? ' premium' : '';

            if ($layout === 'grid') {
                // Grid layout - card style
                ?>
<div class="job-card <?php echo esc_attr($premium_class); ?>">
    <div class="job-card-header">
        <?php if (has_post_thumbnail() || !empty($company)) : ?>
        <div class="company-logo-container">
            <?php if (has_post_thumbnail()) : ?>
            <div class="company-logo">
                <?php the_post_thumbnail('thumbnail'); ?>
            </div>
            <?php elseif (!empty($company)) : ?>
            <div class="company-logo-placeholder">
                <?php echo esc_html(isset($company[0]) ? $company[0] : '?'); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <h3 class="job-title">
            <a
                href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <?php if ($category_name) : ?>
        <div class="job-company">
            <?php esc_html_e('in', 'remote-jobs'); ?>
            <span
                class="job-category"><?php echo esc_html($category_name); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="job-card-meta">
        <div class="job-meta-info">
            <?php if ($location_name || $location_meta) : ?>
            <div class="job-location">
                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <?php
                if ($location_meta) {
                    echo esc_html($location_meta);
                } elseif ($location_name) {
                    echo esc_html($location_name);
                }
                ?>
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
                // List layout - match the original block structure
                echo '<div class="job-row' . esc_attr($premium_class) . '">';

                echo '<div class="job-row-main">';

                // Company logo container - only show if there's a logo or company name
                if (has_post_thumbnail() || !empty($company)) {
                    echo '<div class="company-logo-container">';
                    if (has_post_thumbnail()) {
                        echo '<div class="company-logo">';
                        the_post_thumbnail('thumbnail');
                        echo '</div>';
                    } elseif (!empty($company)) {
                        echo '<div class="company-logo-placeholder">';
                        echo esc_html(isset($company[0]) ? $company[0] : '?');
                        echo '</div>';
                    }
                    echo '</div>';
                }

                echo '<div class="job-row-content">';
                echo '<h3 class="job-title">';
                echo '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
                echo '</h3>';

                // Job company info with category (only show category, no company name)
                if ($categories && !is_wp_error($categories)) {
                    echo '<div class="job-company">';
                    echo 'in ';
                    foreach ($categories as $category) {
                        echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                        break; // Show only first category
                    }
                    echo '</div>';
                }

                // Skills in list layout (use span elements)
                if ($skills && !is_wp_error($skills)) {
                    $skills_list = array_map(function ($term) {
                        return $term->name;
                    }, array_slice($skills, 0, 3));

                    echo '<div class="job-skills">';
                    echo '<span class="skills-label">';
                    echo esc_html__('Skills:', 'remote-jobs');
                    echo '</span>';
                    echo '<span class="skills-list">';
                    echo esc_html(implode(', ', $skills_list));
                    echo '</span>';
                    echo '</div>';
                }

                echo '</div>'; // End job-row-content
                echo '</div>'; // End job-row-main

                echo '<div class="job-row-meta">';

                // Location in list layout (only show if exists)
                if ($location_meta || $locations) {
                    echo '<div class="job-location">';
                    echo '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">';
                    echo '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
                    echo '<circle cx="12" cy="10" r="3"></circle>';
                    echo '</svg>';
                    if ($location_meta) {
                        echo esc_html($location_meta);
                    }
                    if ($locations && !is_wp_error($locations)) {
                        $location_names = array_map(function ($term) {
                            return $term->name;
                        }, $locations);
                        echo esc_html(implode(', ', $location_names));
                    }
                    echo '</div>';
                }

                echo '</div>'; // End job-row-meta

                echo '<div class="job-row-actions">';
                echo '<span class="job-date">' . esc_html(get_the_date()) . '</span>';
                echo '<a href="#" class="save-job-button" data-job-id="' . esc_attr(get_the_ID()) . '" aria-label="' . esc_attr__('Save job', 'remote-jobs') . '">';
                echo '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">';
                echo '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                echo '</svg>';
                echo '</a>';
                echo '</div>';

                echo '</div>'; // End job-row
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

    // If we found a registered script, localize it
    if ($script_handle) {
        wp_localize_script($script_handle, 'remjobsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('filter_jobs_nonce')
        ));
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
        }
    }
}
add_action('wp_enqueue_scripts', 'remjobs_enqueue_job_filter', 20);

// Alternative approach: Hook into block rendering to ensure localization
function remjobs_localize_on_block_render($block_content, $block)
{
    // Only target our specific block
    if (isset($block['blockName']) && $block['blockName'] === 'remjobs/job-list') {

        // Try to localize any script handle that might be our view script
        global $wp_scripts;
        $all_handles = array_keys($wp_scripts->registered);

        // Look for handles that might be our view script
        $view_handles = array_filter($all_handles, function ($handle) {
            return (strpos($handle, 'remjobs') !== false && strpos($handle, 'view') !== false) ||
                   (strpos($handle, 'job-list') !== false && strpos($handle, 'view') !== false) ||
                   strpos($handle, 'remjobs-job-list') !== false;
        });

        // Try to localize all potential handles
        foreach ($view_handles as $handle) {
            if (wp_script_is($handle, 'registered')) {
                wp_localize_script($handle, 'remjobsAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('filter_jobs_nonce')
                ));
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
    // Verify nonce - required for all requests
    $nonce_provided = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    
    if (empty($nonce_provided) || !wp_verify_nonce($nonce_provided, 'filter_jobs_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }

    // Sanitize inputs
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $skills = isset($_POST['skills']) ? sanitize_text_field(wp_unslash($_POST['skills'])) : '';
    $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';
    $layout = isset($_POST['layout']) ? sanitize_text_field(wp_unslash($_POST['layout'])) : 'grid';

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
                // Grid layout - match the original block structure
                echo '<div class="job-card' . esc_attr($premium_class) . '">';

                echo '<div class="job-card-header">';

                // Company logo container (matching original structure)
                if (has_post_thumbnail() || !empty($company)) {
                    echo '<div class="company-logo-container">';
                    if (has_post_thumbnail()) {
                        echo '<div class="company-logo">';
                        the_post_thumbnail('thumbnail');
                        echo '</div>';
                    } elseif (!empty($company)) {
                        echo '<div class="company-logo-placeholder">';
                        echo esc_html(isset($company[0]) ? $company[0] : '?');
                        echo '</div>';
                    }
                    echo '</div>';
                }

                echo '<h3 class="job-title">';
                echo '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
                echo '</h3>';

                // Job company info with category (only show category)
                if ($categories && !is_wp_error($categories)) {
                    echo '<div class="job-company">';
                    echo 'in ';
                    foreach ($categories as $category) {
                        echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                        break; // Show only first category
                    }
                    echo '</div>';
                }

                echo '</div>'; // End job-card-header

                echo '<div class="job-card-meta">';
                echo '<div class="job-meta-info">';

                // Location (only show if exists)
                if ($location_meta || $locations) {
                    echo '<div class="job-location">';
                    echo '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">';
                    echo '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
                    echo '<circle cx="12" cy="10" r="3"></circle>';
                    echo '</svg>';
                    if ($location_meta) {
                        echo esc_html($location_meta);
                    }
                    if ($locations && !is_wp_error($locations)) {
                        $location_names = array_map(function ($term) {
                            return $term->name;
                        }, $locations);
                        echo esc_html(implode(', ', $location_names));
                    }
                    echo '</div>';
                }

                echo '</div>'; // End job-meta-info

                // Skills (always show if available)
                if ($skills && !is_wp_error($skills)) {
                    $skills_list = array_map(function ($term) {
                        return $term->name;
                    }, array_slice($skills, 0, 3)); // Show max 3 skills

                    echo '<div class="job-skills">';
                    echo '<div class="skills-label">';
                    echo esc_html__('Skills:', 'remote-jobs');
                    echo '</div>';
                    echo '<div class="skills-list">';
                    echo esc_html(implode(', ', $skills_list));
                    echo '</div>';
                    echo '</div>';
                }

                echo '</div>'; // End job-card-meta

                echo '<div class="job-card-footer">';
                echo '<a href="#" class="save-job-button" data-job-id="' . esc_attr(get_the_ID()) . '" aria-label="' . esc_attr__('Save job', 'remote-jobs') . '">';
                echo '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">';
                echo '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                echo '</svg>';
                echo '</a>';
                echo '</div>';

                echo '</div>'; // End job-card
            } else {
                // List layout - match the original block structure
                echo '<div class="job-row' . esc_attr($premium_class) . '">';

                echo '<div class="job-row-main">';

                // Company logo container (matching original structure)
                if (has_post_thumbnail() || !empty($company)) {
                    echo '<div class="company-logo-container">';
                    if (has_post_thumbnail()) {
                        echo '<div class="company-logo">';
                        the_post_thumbnail('thumbnail');
                        echo '</div>';
                    } elseif (!empty($company)) {
                        echo '<div class="company-logo-placeholder">';
                        echo esc_html(isset($company[0]) ? $company[0] : '?');
                        echo '</div>';
                    }
                    echo '</div>';
                }

                echo '<div class="job-row-content">';
                echo '<h3 class="job-title">';
                echo '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>';
                echo '</h3>';

                // Job company info with category (only show category, no company name)
                if ($categories && !is_wp_error($categories)) {
                    echo '<div class="job-company">';
                    echo 'in ';
                    foreach ($categories as $category) {
                        echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                        break; // Show only first category
                    }
                    echo '</div>';
                }

                // Skills in list layout (use span elements)
                if ($skills && !is_wp_error($skills)) {
                    $skills_list = array_map(function ($term) {
                        return $term->name;
                    }, array_slice($skills, 0, 3));

                    echo '<div class="job-skills">';
                    echo '<span class="skills-label">';
                    echo esc_html__('Skills:', 'remote-jobs');
                    echo '</span>';
                    echo '<span class="skills-list">';
                    echo esc_html(implode(', ', $skills_list));
                    echo '</span>';
                    echo '</div>';
                }

                echo '</div>'; // End job-row-content
                echo '</div>'; // End job-row-main

                echo '<div class="job-row-meta">';

                // Location in list layout (only show if exists)
                if ($location_meta || $locations) {
                    echo '<div class="job-location">';
                    echo '<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none">';
                    echo '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>';
                    echo '<circle cx="12" cy="10" r="3"></circle>';
                    echo '</svg>';
                    if ($location_meta) {
                        echo esc_html($location_meta);
                    }
                    if ($locations && !is_wp_error($locations)) {
                        $location_names = array_map(function ($term) {
                            return $term->name;
                        }, $locations);
                        echo esc_html(implode(', ', $location_names));
                    }
                    echo '</div>';
                }

                echo '</div>'; // End job-row-meta

                echo '<div class="job-row-actions">';
                echo '<span class="job-date">' . esc_html(get_the_date()) . '</span>';
                echo '<a href="#" class="save-job-button" data-job-id="' . esc_attr(get_the_ID()) . '" aria-label="' . esc_attr__('Save job', 'remote-jobs') . '">';
                echo '<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none">';
                echo '<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>';
                echo '</svg>';
                echo '</a>';
                echo '</div>';

                echo '</div>'; // End job-row
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
            $message = esc_html(sprintf(
                /* translators: %s: Comma-separated list of active filters (e.g., "category 'PHP', location 'Remote'") */
                __('No jobs found matching your filters: %s. Try adjusting your search criteria.', 'remote-jobs'),
                implode(', ', array_map('esc_html', $filter_descriptions))
            ));
        } else {
            $message = esc_html__('No jobs found. Please try different search criteria.', 'remote-jobs');
        }

        echo '<p>' . esc_html($message) . '</p>';
        echo '</div>';
    }

    echo '</div>'; // End job listings

    wp_reset_postdata();
    $output = ob_get_clean();

    wp_send_json_success(array(
        'data' => $output,
        'found' => $query->found_posts
    ));
}

add_action('wp_ajax_remjobs_ajax_filter_jobs', 'remjobs_filter_jobs');
add_action('wp_ajax_nopriv_remjobs_ajax_filter_jobs', 'remjobs_filter_jobs');

// Additional AJAX endpoint to provide nonces
function remjobs_get_nonce()
{
    wp_send_json_success(array(
        'nonce' => wp_create_nonce('filter_jobs_nonce'),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_ajax_remjobs_ajax_get_nonce', 'remjobs_get_nonce');
add_action('wp_ajax_nopriv_remjobs_ajax_get_nonce', 'remjobs_get_nonce');
?>