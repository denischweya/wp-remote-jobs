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
?>