<?php
/**
 * REST API endpoints for Remote Jobs
 *
 * @package RemJobs
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Register REST API routes for RemJobs
 */
function remjobs_register_rest_routes()
{
    register_rest_route('remjobs/v1', '/jobs', array(
        'methods' => 'GET',
        'callback' => 'remjobs_get_jobs',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'remjobs_register_rest_routes');

/**
 * Callback for the jobs endpoint
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response Response object.
 */
function remjobs_get_jobs($request)
{
    $args = array(
        'post_type' => 'remjobs_jobs', // Use consistent post type name
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $jobs = get_posts($args);
    $data = array();

    foreach ($jobs as $job) {
        // Properly sanitize and escape data for REST API
        $data[] = array(
            'id' => absint($job->ID),
            'title' => wp_kses_post($job->post_title),
            'excerpt' => wp_kses_post(get_the_excerpt($job)),
            'link' => esc_url_raw(get_permalink($job->ID)),
            'company_name' => sanitize_text_field(get_post_meta($job->ID, '_remjobs_company_name', true)),
            'application_link' => esc_url_raw(get_post_meta($job->ID, '_remjobs_application_link', true)),
            'categories' => array_map('sanitize_text_field', wp_get_post_terms($job->ID, 'remjobs_job_category', array('fields' => 'names'))),
            'location' => array_map('sanitize_text_field', wp_get_post_terms($job->ID, 'remjobs_job_location', array('fields' => 'names'))),
            'skills' => array_map('sanitize_text_field', wp_get_post_terms($job->ID, 'remjobs_job_skills', array('fields' => 'names')))
        );
    }

    return rest_ensure_response($data);
}
