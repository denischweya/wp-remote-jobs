<?php

// Include individual block files
require_once __DIR__ . '/submit-job/submit-job.php';
require_once __DIR__ . '/job-list/job-list.php';
require_once __DIR__ . '/job-sidebar/job-sidebar.php';
require_once __DIR__ . '/registration/registration.php';

function remjobs_register_blocks() {
    // Register Submit Job Block
    register_block_type(__DIR__ . '/submit-job/build', array(
        'render_callback' => 'render_submit_job_block',
    ));

    // Register Job List Block
    require_once __DIR__ . '/job-list/rest-api.php';
    register_block_type(__DIR__ . '/job-list/build', array(
        'render_callback' => 'remjobs_render_job_listings_block',
        'api_version' => 3
    ));

    // Register Job Sidebar Block
    register_block_type(__DIR__ . '/job-sidebar/build', array(
        'render_callback' => 'render_job_sidebar_block',
        'api_version' => 3
    ));

    // Registration Block is registered in its own file
}

add_action('init', 'remjobs_register_blocks');