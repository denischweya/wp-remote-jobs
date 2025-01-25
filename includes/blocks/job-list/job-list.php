<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

function remjobs_render_job_listings_block($attributes, $content, $block) {
    ob_start();
    ?>
    <div <?php echo get_block_wrapper_attributes(['class' => 'job-listings-container']); ?>>
        <?php
        $args = array(
            'post_type' => 'jobs',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        );

        $jobs = new WP_Query($args);

        if ($jobs->have_posts()) :
            ?>
            <div class="job-listings-grid">
                <?php
                while ($jobs->have_posts()) : $jobs->the_post();
                    $company_name = get_post_meta(get_the_ID(), 'company_name', true);
                    $employment_type = get_post_meta(get_the_ID(), '_employment_type', true);
                    $application_link = get_post_meta(get_the_ID(), '_application_link', true);
                    ?>
                    <div class="job-card">
                        <div class="job-card-header">
                            <h3 class="job-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <?php if ($company_name) : ?>
                                <span class="company-name"><?php echo esc_html($company_name); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="job-card-meta">
                            <?php if ($employment_type) : ?>
                                <span class="job-type">
                                    <i class="job-type-icon"></i>
                                    <?php echo esc_html($employment_type); ?>
                                </span>
                            <?php endif; ?>
                            
                            <div class="job-card-actions">
                                <?php if ($application_link) : ?>
                                    <a href="<?php echo esc_url($application_link); ?>" class="apply-button" target="_blank">
                                        <?php esc_html_e('Apply Now', 'remote-jobs'); ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else : ?>
            <div class="no-jobs-found">
                <p><?php esc_html_e('No jobs found.', 'remote-jobs'); ?></p>
            </div>
        <?php
        endif;
        wp_reset_postdata();
        ?>
    </div>
    <?php
    return ob_get_clean();
}

function enqueue_job_list_assets() {
    $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');
    
    wp_enqueue_style(
        'job-list-style',
        plugins_url('build/style-index.css', __FILE__),
        [],
        $asset_file['version']
    );
}
add_action('init', 'enqueue_job_list_assets');