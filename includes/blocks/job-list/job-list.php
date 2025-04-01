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
    $layout = isset($attributes['layout']) ? $attributes['layout'] : 'grid';
    $posts_per_page = isset($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 10;
    $show_toggle = isset($attributes['showToggle']) ? $attributes['showToggle'] : true;
    $show_filters = isset($attributes['showFilters']) ? $attributes['showFilters'] : false;
    $featured_only = isset($attributes['featuredOnly']) ? $attributes['featuredOnly'] : false;

    // Filter visibility settings
    $show_category_filter = isset($attributes['showCategoryFilter']) ? $attributes['showCategoryFilter'] : true;
    $show_location_filter = isset($attributes['showLocationFilter']) ? $attributes['showLocationFilter'] : true;
    $show_skills_filter = isset($attributes['showSkillsFilter']) ? $attributes['showSkillsFilter'] : false;
    $show_search_filter = isset($attributes['showSearchFilter']) ? $attributes['showSearchFilter'] : true;

    // Taxonomy filters
    $filter_by_category = isset($attributes['filterByCategory']) ? $attributes['filterByCategory'] : array();
    $filter_by_skills = isset($attributes['filterBySkills']) ? $attributes['filterBySkills'] : array();
    $filter_by_location = isset($attributes['filterByLocation']) ? $attributes['filterByLocation'] : array();

    // Start output buffer
    ob_start();

    // Check if we have any jobs before proceeding
    $check_jobs = new WP_Query(array(
        'post_type' => 'jobs',
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

    // Handle URL parameters for filtering
    $url_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $url_location = isset($_GET['location']) ? sanitize_text_field($_GET['location']) : '';
    $url_skills = isset($_GET['skills']) ? sanitize_text_field($_GET['skills']) : '';
    $url_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

    // Apply URL filters if they exist
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
        'post_type'      => 'jobs',
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
            'taxonomy' => 'job_category',
            'field'    => 'slug',
            'terms'    => $filter_by_category,
        );
    }

    if (! empty($filter_by_skills)) {
        $tax_query[] = array(
            'taxonomy' => 'job_skills',
            'field'    => 'slug',
            'terms'    => $filter_by_skills,
        );
    }

    if (! empty($filter_by_location)) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
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
        'post_type'      => 'jobs',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    );
    $total_jobs = new WP_Query($job_count_args);
    $total_jobs_count = $total_jobs->found_posts;

    // Get today's job count
    $today = getdate();
    $today_job_args = array(
        'post_type'      => 'jobs',
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
    $container_attrs = sprintf(
        'data-layout="%s" id="%s"',
        esc_attr($layout),
        esc_attr($block_id)
    );

    echo '<div class="' . esc_attr($container_class) . '" ' . $container_attrs . '>';

    // Display the header with job count and layout toggle
    echo '<div class="job-listings-header">';

    // Job count display
    echo '<div class="job-count">';
    echo '<h2 class="job-count-title">' . esc_html__('Latest jobs', 'remote-jobs') . '</h2>';

    // Format job counts
    echo '<p class="job-count-stats">';

    // Properly escape the count output
    echo esc_html(
        sprintf(
            _n('%d job live', '%d jobs live', $total_jobs_count, 'remote-jobs'),
            $total_jobs_count
        )
    );

    if ($today_jobs_count > 0) {
        echo ' - ';
        echo esc_html(
            sprintf(
                _n('%d added today', '%d added today', $today_jobs_count, 'remote-jobs'),
                $today_jobs_count
            )
        );
    }

    echo '</p>';
    echo '</div>'; // End job count

    // Layout toggle buttons
    if ($show_toggle) {
        echo '<div class="view-toggle">';

        // View all jobs link
        echo '<a href="' . esc_url(get_post_type_archive_link('jobs')) . '" class="view-all-link">';
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
            'taxonomy'   => 'remjobs_location',
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
            echo '<input type="text" class="search-input" id="job-search-' . esc_attr($block_id) . '" placeholder="' . esc_attr__('Search jobs...', 'remote-jobs') . '"' . $search_value . '>';
        }

        // Category dropdown
        if ($show_category_filter && !is_wp_error($categories) && !empty($categories)) {
            echo '<select class="category-select" id="category-select-' . esc_attr($block_id) . '">';
            echo '<option value="">' . esc_html__('All Categories', 'remote-jobs') . '</option>';

            foreach ($categories as $category) {
                $selected = ($url_category === $category->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($category->slug) . '"' . $selected . '>' . esc_html($category->name) . '</option>';
            }

            echo '</select>';
        }

        // Location dropdown
        if ($show_location_filter && !is_wp_error($locations) && !empty($locations)) {
            echo '<select class="location-select" id="location-select-' . esc_attr($block_id) . '">';
            echo '<option value="">' . esc_html__('All Locations', 'remote-jobs') . '</option>';

            foreach ($locations as $location) {
                $selected = ($url_location === $location->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($location->slug) . '"' . $selected . '>' . esc_html($location->name) . '</option>';
            }

            echo '</select>';
        }

        // Skills dropdown
        if ($show_skills_filter && !is_wp_error($skills) && !empty($skills)) {
            echo '<select class="skills-select" id="skills-select-' . esc_attr($block_id) . '">';
            echo '<option value="">' . esc_html__('All Skills', 'remote-jobs') . '</option>';

            foreach ($skills as $skill) {
                $selected = ($url_skills === $skill->slug) ? ' selected' : '';
                echo '<option value="' . esc_attr($skill->slug) . '"' . $selected . '>' . esc_html($skill->name) . '</option>';
            }

            echo '</select>';
        }

        // Filter button
        echo '<button type="button" class="filter-button" id="filter-button-' . esc_attr($block_id) . '">';
        echo esc_html__('Filter', 'remote-jobs');
        echo '</button>';

        echo '</div>'; // End filter row
        echo '</div>'; // End job filters
    }

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
            $job_locations = get_the_terms(get_the_ID(), 'remjobs_location');
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
                                    echo esc_html(
                                        sprintf(
                                            _n('%d day left to apply', '%d days left to apply', $days_left, 'remote-jobs'),
                                            $days_left
                                        )
                                    );
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
                                    echo esc_html(
                                        sprintf(
                                            _n('%d day left to apply', '%d days left to apply', $days_left, 'remote-jobs'),
                                            $days_left
                                        )
                                    );
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

            echo paginate_links(array(
                'base'      => get_pagenum_link(1) . '%_%',
                'format'    => '?paged=%#%',
                'current'   => $current_page,
                'total'     => $total_pages,
                'prev_text' => '&laquo; ' . __('Previous', 'remote-jobs'),
                'next_text' => __('Next', 'remote-jobs') . ' &raquo;',
            ));

            echo '</div>';
        }
    } else {
        // No jobs found matching criteria (different from no jobs at all)
        echo '<div class="no-jobs-found">';
        echo '<p>' . esc_html__('No jobs found matching your criteria.', 'remote-jobs') . '</p>';
        echo '</div>';
    }

    echo '</div>'; // End job listings
    echo '</div>'; // End container

    return ob_get_clean();
}
?>