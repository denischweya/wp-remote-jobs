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

            // Get job meta data
            $company_name = get_post_meta(get_the_ID(), '_remjobs_company_name', true);
            $job_type     = get_post_meta(get_the_ID(), '_remjobs_job_type', true);
            $salary       = get_post_meta(get_the_ID(), '_remjobs_salary', true);
            $application_url = get_post_meta(get_the_ID(), '_remjobs_application_url', true);
            $is_premium   = get_post_meta(get_the_ID(), '_remjobs_is_premium', true) === '1';

            // Get categories/taxonomies
            $job_categories = get_the_terms(get_the_ID(), 'remjobs_job_category');
            $job_locations = get_the_terms(get_the_ID(), 'remjobs_job_location');
            $job_skills = get_the_terms(get_the_ID(), 'remjobs_job_skills');

            $category_name = '';
            if (!is_wp_error($job_categories) && !empty($job_categories)) {
                $category_name = $job_categories[0]->name;
            }

            $location_name = '';
            if (!is_wp_error($job_locations) && !empty($job_locations)) {
                $location_name = $job_locations[0]->name;
            }

            $skills_list = '';
            if (!is_wp_error($job_skills) && !empty($job_skills)) {
                foreach ($job_skills as $skill) {
                    $skills_list .= $skill->name . ', ';
                }
                $skills_list = rtrim($skills_list, ', ');
            }

            $premium_class = $is_premium ? ' premium' : '';

            if ($layout === 'grid') {
                // Grid layout
                echo '<div class="job-card' . esc_attr($premium_class) . '">';

                echo '<div class="job-card-header">';
                echo '<h3 class="job-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
                if ($company_name) {
                    echo '<div class="job-company">';
                    echo '<span class="company-name">' . esc_html($company_name) . '</span>';
                    if ($category_name) {
                        echo ' • <span class="job-category">' . esc_html($category_name) . '</span>';
                    }
                    echo '</div>';
                }
                echo '</div>';

                echo '<div class="job-card-meta">';
                echo '<div class="job-meta-info">';

                if ($job_type) {
                    echo '<span class="job-type-badge">' . esc_html($job_type) . '</span>';
                }

                if ($location_name) {
                    echo '<div class="job-location">' . esc_html($location_name) . '</div>';
                }

                if ($salary) {
                    echo '<div class="job-salary">' . esc_html($salary) . '</div>';
                }

                echo '</div>';

                if ($skills_list) {
                    echo '<div class="job-skills">';
                    echo '<span class="skills-label">' . esc_html__('Skills:', 'remote-jobs') . '</span> ';
                    echo '<span class="skills-list">' . esc_html($skills_list) . '</span>';
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

                echo '<div class="job-row-main">';
                echo '<div class="job-row-content">';
                echo '<h3 class="job-title"><a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a></h3>';
                if ($company_name) {
                    echo '<div class="job-company">';
                    echo '<span class="company-name">' . esc_html($company_name) . '</span>';
                    if ($category_name) {
                        echo ' • <span class="job-category">' . esc_html($category_name) . '</span>';
                    }
                    echo '</div>';
                }

                if ($skills_list) {
                    echo '<div class="job-skills">';
                    echo '<span class="skills-label">' . esc_html__('Skills:', 'remote-jobs') . '</span>';
                    echo '<span class="skills-list">' . esc_html($skills_list) . '</span>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';

                echo '<div class="job-row-meta">';
                if ($job_type) {
                    echo '<span class="job-type-badge">' . esc_html($job_type) . '</span>';
                }

                if ($location_name) {
                    echo '<div class="job-location">' . esc_html($location_name) . '</div>';
                }

                if ($salary) {
                    echo '<div class="job-salary">' . esc_html($salary) . '</div>';
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