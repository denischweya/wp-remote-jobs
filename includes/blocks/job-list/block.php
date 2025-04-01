<?php


/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */


function remjobs_render_job_listings_block($attributes)
{
    // Extract attributes with defaults - using proper WP attribute handling
    $block_title = !empty($attributes['blockTitle']) ? $attributes['blockTitle'] : __('Latest jobs', 'remote-jobs');
    $background_color = !empty($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '#f7f9fc';
    $view_all_page_id = !empty($attributes['viewAllJobsPage']) ? intval($attributes['viewAllJobsPage']) : 0;

    // Get the view all jobs URL
    $view_all_url = $view_all_page_id > 0 ? get_permalink($view_all_page_id) : '';

    // Prepare taxonomies for dropdowns
    $job_categories = get_terms(array(
        'taxonomy' => 'job_category',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    $job_skills = get_terms(array(
        'taxonomy' => 'job_skills',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    $locations = get_terms(array(
        'taxonomy' => 'job_location',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    // Query for jobs
    $args = array(
        'post_type' => 'jobs',
        'posts_per_page' => isset($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 10,
        'post_status' => 'publish',
    );

    // Add taxonomy filters if set in attributes
    $tax_query = array();

    if (!empty($attributes['filterByCategory'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_category',
            'field' => 'slug',
            'terms' => $attributes['filterByCategory'],
        );
    }

    if (!empty($attributes['filterBySkills'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_skills',
            'field' => 'slug',
            'terms' => $attributes['filterBySkills'],
        );
    }

    if (!empty($attributes['filterByLocation'])) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => $attributes['filterByLocation'],
        );
    }

    if (!empty($tax_query)) {
        $tax_query['relation'] = 'AND';
        $args['tax_query'] = $tax_query;
    }

    $jobs = new WP_Query($args);
    $total_jobs = $jobs->found_posts;

    // Add inline style for background color without escaping the rgba format
    $block_style = "background-color: " . esc_attr($background_color) . ";";

    ob_start();
    ?>
<div class="job-listings-container" style="<?php echo $block_style; ?>">
    <?php if ($jobs->have_posts()) : ?>
    <div class="job-listings-header">
        <div class="job-count">
            <h2 class="job-count-title"><?php echo esc_html($block_title); ?></h2>
            <p class="job-count-stats">
                <?php
                            printf(
                                esc_html(_n('%d job available', '%d jobs available', $total_jobs, 'remote-jobs')),
                                $total_jobs
                            );
        ?>
            </p>
        </div>
        <?php if ($view_all_url) : ?>
        <div class="view-all-jobs">
            <a href="<?php echo esc_url($view_all_url); ?>"
                class="view-all-link">
                <?php esc_html_e('View all jobs', 'remote-jobs'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isset($attributes['showFilters']) && $attributes['showFilters']) : ?>
    <div class="job-search-form"
        data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
        <?php wp_nonce_field('filter_jobs_nonce', 'jobs_filter_nonce'); ?>

        <?php if (isset($attributes['showSearchFilter']) && $attributes['showSearchFilter']) : ?>
        <input type="text" id="job-search"
            placeholder="<?php esc_attr_e('Search jobs...', 'remote-jobs'); ?>" />
        <?php endif; ?>

        <?php if (isset($attributes['showCategoryFilter']) && $attributes['showCategoryFilter']) : ?>
        <select id="job-category">
            <option value="">
                <?php esc_html_e('All Categories', 'remote-jobs'); ?>
            </option>
            <?php foreach ($job_categories as $category) : ?>
            <option value="<?php echo esc_attr($category->slug); ?>">
                <?php echo esc_html($category->name); ?>
                (<?php echo esc_html($category->count); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if (isset($attributes['showLocationFilter']) && $attributes['showLocationFilter']) : ?>
        <select id="job-location">
            <option value="">
                <?php esc_html_e('All Locations', 'remote-jobs'); ?>
            </option>
            <?php foreach ($locations as $location) : ?>
            <option value="<?php echo esc_attr($location->slug); ?>">
                <?php echo esc_html($location->name); ?>
                (<?php echo esc_html($location->count); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <?php if (isset($attributes['showSkillsFilter']) && $attributes['showSkillsFilter']) : ?>
        <select id="job-skills">
            <option value="">
                <?php esc_html_e('All Skills', 'remote-jobs'); ?>
            </option>
            <?php foreach ($job_skills as $skill) : ?>
            <option value="<?php echo esc_attr($skill->slug); ?>">
                <?php echo esc_html($skill->name); ?>
                (<?php echo esc_html($skill->count); ?>)
            </option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>

        <button type="button"
            id="filter-jobs"><?php esc_html_e('Filter', 'remote-jobs'); ?></button>
    </div>
    <?php endif; ?>

    <div id="jobs-list"
        class="jobs-list <?php echo esc_attr($attributes['layout'] ?? 'grid'); ?>">
        <?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
        <div class="job-card">
            <h3><a
                    href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
            </h3>
            <?php the_excerpt(); ?>
            <div class="job-meta">
                <?php
                $categories = get_the_terms(get_the_ID(), 'job_category');
            if ($categories && !is_wp_error($categories)) :
                echo '<span class="job-category">' . esc_html($categories[0]->name) . '</span>';
            endif;
            ?>
                <span
                    class="job-date"><?php echo get_the_date(); ?></span>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php wp_reset_postdata(); ?>
    <?php else : ?>
    <p><?php esc_html_e('No jobs found matching your criteria.', 'remote-jobs'); ?>
    </p>
    <?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function remjobs_enqueue_job_filter()
{
    wp_enqueue_script('remjobs-filter', plugin_dir_url(__FILE__) . '/src/view.js', array('jquery'), '1.0', true);

    // Add the ajaxurl variable to our script
    wp_localize_script('remjobs-filter', 'remjobsAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('filter_jobs_nonce')
    ));
}
add_action('wp_enqueue_scripts', 'remjobs_enqueue_job_filter');

function remjobs_filter_jobs()
{
    // Verify nonce
    check_ajax_referer('filter_jobs_nonce', 'nonce');

    // Debug received data
    error_log('Received filter request with data: ' . print_r($_POST, true));

    // Sanitize inputs
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $skills = isset($_POST['skills']) ? sanitize_text_field(wp_unslash($_POST['skills'])) : '';
    $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';

    // Debug sanitized inputs
    error_log('Sanitized inputs: ' . print_r([
        'search' => $search,
        'category' => $category,
        'skills' => $skills,
        'location' => $location
    ], true));

    // Build query args
    $args = array(
        'post_type' => 'jobs',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );

    // Add search if provided
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Build taxonomy query
    $tax_query = array();

    if (!empty($category)) {
        $tax_query[] = array(
            'taxonomy' => 'job_category',
            'field' => 'slug',
            'terms' => $category,
            'operator' => 'IN'
        );
    }

    if (!empty($skills)) {
        $tax_query[] = array(
            'taxonomy' => 'job_skills',
            'field' => 'slug',
            'terms' => $skills,
            'operator' => 'IN'
        );
    }

    if (!empty($location)) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => $location,
            'operator' => 'IN'
        );
    }

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $args['tax_query'] = $tax_query;
    }

    // Debug final query args
    error_log('Final WP_Query args: ' . print_r($args, true));

    // Execute query
    $query = new WP_Query($args);

    // Debug query results
    error_log('Query found ' . $query->found_posts . ' posts');
    error_log('Query SQL: ' . $query->request);

    ob_start();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            ?>
<div class="job-card">
    <h3><a
            href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h3>
    <?php the_excerpt(); ?>
    <div class="job-meta">
        <?php
                    // Get and display job categories
                    $categories = get_the_terms(get_the_ID(), 'job_category');
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                }
            }

            // Get and display job skills
            $skills = get_the_terms(get_the_ID(), 'job_skills');
            if ($skills && !is_wp_error($skills)) {
                foreach ($skills as $skill) {
                    echo '<span class="job-skill">' . esc_html($skill->name) . '</span>';
                }
            }
            ?>
        <span class="job-date"><?php echo get_the_date(); ?></span>
    </div>
</div>
<?php
        }
    } else {
        echo '<p>' . esc_html__('No jobs found matching your criteria.', 'remote-jobs') . '</p>';
        // Debug taxonomies
        error_log('Available terms in job_category: ' . print_r(get_terms(['taxonomy' => 'job_category', 'hide_empty' => false]), true));
        error_log('Available terms in job_skills: ' . print_r(get_terms(['taxonomy' => 'job_skills', 'hide_empty' => false]), true));
        error_log('Available terms in job_location: ' . print_r(get_terms(['taxonomy' => 'job_location', 'hide_empty' => false]), true));
    }

    wp_reset_postdata();
    $output = ob_get_clean();

    wp_send_json_success([
        'data' => $output,
        'found' => $query->found_posts,
        'debug' => [
            'args' => $args,
            'sql' => $query->request
        ]
    ]);
}

add_action('wp_ajax_filter_jobs', 'remjobs_filter_jobs');
add_action('wp_ajax_nopriv_filter_jobs', 'remjobs_filter_jobs');
?>