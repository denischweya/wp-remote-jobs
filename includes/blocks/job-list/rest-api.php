<?php

function remjobs_register_rest_routes() {
    register_rest_route('wp-remote-jobs/v1', '/jobs', array(
        'methods' => 'GET',
        'callback' => 'remjobs_get_jobs',
        'permission_callback' => '__return_true'
    ));
}
add_action('rest_api_init', 'remjobs_register_rest_routes');

function remjobs_get_jobs($request) {
    $args = array(
        'post_type' => 'jobs',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );

    $query = new WP_Query($args);
    $jobs = array();

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $jobs[] = array(
                'id' => get_the_ID(),
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'link' => get_permalink(),
                'date' => get_the_date('c'),
                'categories' => wp_get_post_terms(get_the_ID(), 'job_category', array('fields' => 'names')),
                'location' => wp_get_post_terms(get_the_ID(), 'job_location', array('fields' => 'names')),
                'skills' => wp_get_post_terms(get_the_ID(), 'job_skills', array('fields' => 'names'))
            );
        }
        wp_reset_postdata();
    }

    return rest_ensure_response($jobs);
}