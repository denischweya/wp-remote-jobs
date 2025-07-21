<?php

/**
 * Compatibility file for job-list block
 *
 * This file serves as a bridge to ensure that any code calling the old function name
 * will still work, but will use the new implementation from job-list.php.
 *
 * @package RemJobs
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render the job list block - redirects to the main rendering function in job-list.php
 *
 * @param array $attributes Block attributes.
 * @return string Block HTML.
 */
function remjobs_render_job_listings_block($attributes)
{
    // This function exists for backward compatibility
    // It redirects to the main rendering function in job-list.php
    if (function_exists('remjobs_render_job_list_block')) {
        return remjobs_render_job_list_block($attributes);
    }

    // Fallback implementation in case the main function is not available
    $block_title = !empty($attributes['blockTitle']) ? $attributes['blockTitle'] : __('Latest jobs', 'remote-jobs');
    $background_color = !empty($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '#f7f9fc';

    ob_start();
    ?>
<div class="job-listings-container"
    style="background-color: <?php echo esc_attr($background_color); ?>;">
    <h2 class="job-count-title"><?php echo esc_html($block_title); ?>
    </h2>
    <p><?php esc_html_e('Please check plugin configuration. Main rendering function is missing.', 'remote-jobs'); ?>
    </p>
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
    if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'filter_jobs_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
        return;
    }

    // Sanitize inputs
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $category = isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : '';
    $skills = isset($_POST['skills']) ? sanitize_text_field(wp_unslash($_POST['skills'])) : '';
    $location = isset($_POST['location']) ? sanitize_text_field(wp_unslash($_POST['location'])) : '';

    // Build query args
    $args = array(
        'post_type' => 'remjobs_jobs',
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
            'taxonomy' => 'remjobs_job_category',
            'field' => 'slug',
            'terms' => explode(',', $category),
            'operator' => 'IN'
        );
    }

    if (!empty($skills)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_skills',
            'field' => 'slug',
            'terms' => explode(',', $skills),
            'operator' => 'IN'
        );
    }

    if (!empty($location)) {
        $tax_query[] = array(
            'taxonomy' => 'remjobs_job_location',
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
                    $categories = get_the_terms(get_the_ID(), 'remjobs_job_category');
            if ($categories && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    echo '<span class="job-category">' . esc_html($category->name) . '</span>';
                }
            }

            // Get and display job skills
            $skills = get_the_terms(get_the_ID(), 'remjobs_job_skills');
            if ($skills && !is_wp_error($skills)) {
                foreach ($skills as $skill) {
                    echo '<span class="job-skill">' . esc_html($skill->name) . '</span>';
                }
            }
            ?>
        <span class="job-date"><?php echo esc_html(get_the_date()); ?></span>
    </div>
</div>
<?php
        }
    } else {
        echo '<p>' . esc_html__('No jobs found matching your criteria.', 'remote-jobs') . '</p>';
    }

    wp_reset_postdata();
    $output = ob_get_clean();

    wp_send_json_success(array(
        'data' => $output,
        'found' => $query->found_posts
    ));
}

add_action('wp_ajax_filter_jobs', 'remjobs_filter_jobs');
add_action('wp_ajax_nopriv_filter_jobs', 'remjobs_filter_jobs');
?>