<?php
/**
 * Register blocks for RemJobs
 *
 * @package RemJobs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Include individual block files
require_once __DIR__ . '/submit-job/submit-job.php';
require_once __DIR__ . '/job-list/job-list.php';
require_once __DIR__ . '/job-sidebar/job-sidebar.php';
require_once __DIR__ . '/registration/registration.php';

function remjobs_register_blocks()
{
    // Register Submit Job Block
    register_block_type(__DIR__ . '/submit-job/build', array(
        'render_callback' => 'remjobs_render_submit_job_block',
    ));

    // Job List Block is registered in its own file via remjobs_register_job_list_block()
    // Just include the REST API endpoints
    require_once __DIR__ . '/job-list/rest-api.php';

    // Register Job Sidebar Block
    register_block_type(__DIR__ . '/job-sidebar/build', array(
        'render_callback' => 'remjobs_render_job_sidebar_block',
        'api_version' => 3
    ));

    // Registration Block is registered in its own file via remjobs_registration_block_init()
}

add_action('init', 'remjobs_register_blocks');
