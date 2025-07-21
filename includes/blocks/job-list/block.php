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