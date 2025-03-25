<?php

function remjobs_register_rest_routes()
{
    register_rest_route('remjobs/v1', '/jobs', array(
        'methods' => 'GET',
        'callback' => 'remjobs_get_jobs',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'remjobs_register_rest_routes');

function remjobs_get_jobs($request)
{
    $args = array(
        'post_type' => 'jobs',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $jobs = get_posts($args);
    $data = array();

    foreach ($jobs as $job) {
        $data[] = array(
            'id' => $job->ID,
            'title' => $job->post_title,
            'excerpt' => get_the_excerpt($job),
            'link' => get_permalink($job->ID),
            'company_name' => get_post_meta($job->ID, 'company_name', true),
            'application_link' => get_post_meta($job->ID, 'application_link', true),
            'categories' => wp_get_post_terms($job->ID, 'remjobs_job_category', array('fields' => 'names')),
            'location' => wp_get_post_terms($job->ID, 'remjobs_job_location', array('fields' => 'names')),
            'skills' => wp_get_post_terms($job->ID, 'remjobs_job_skills', array('fields' => 'names'))
        );
    }

    return rest_ensure_response($data);
}
