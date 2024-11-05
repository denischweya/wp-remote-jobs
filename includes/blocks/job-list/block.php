<?php


/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */


function render_job_listings_block($attributes)
{
    // Prepare taxonomies for dropdowns
    $job_categories = get_terms(array(
        'taxonomy' => 'job_category',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    $employment_types = get_terms(array(
        'taxonomy' => 'employment_type',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    $locations = get_terms(array(
        'taxonomy' => 'job_location',
        'hide_empty' => true,
        'fields' => 'all',
        'count' => true,
    ));

    // Query for jobs
    $args = array(
        'post_type' => 'jobs', // Make sure this matches your custom post type name
        'posts_per_page' => -1, // Adjust as needed
    );
    $jobs = new WP_Query($args);

    ob_start();
    ?>
<div class="job-search-form"
	data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
	<input type="text" id="job-search" placeholder="Search" />
	<select id="job-category">
		<option value="">All Job Categories</option>
		<?php
        if (!is_wp_error($job_categories) && !empty($job_categories)) {
            foreach ($job_categories as $category) {
                if ($category) {
                    echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                }
            }
        }
    ?>
	</select>
	<select id="job-type">
		<option value="">All Job Types</option>
		<?php
    if (!is_wp_error($employment_types) && !empty($employment_types)) {
        foreach ($employment_types as $type) {
            if ($type) {
                echo '<option value="' . esc_attr($type->slug) . '">' . esc_html($type->name) . '</option>';
            }
        }
    }
    ?>
	</select>
	<select id="job-location">
		<option value="">All Job Locations</option>
		<?php
    if (!is_wp_error($locations) && !empty($locations)) {
        foreach ($locations as $location) {
            if ($location) {
                echo '<option value="' . esc_attr($location->slug) . '">' . esc_html($location->name) . '</option>';
            }
        }
    }
    ?>
	</select>
</div>
<div id="job-listings" class="job-listings">
	<?php if ($jobs->have_posts()) : ?>
	<?php while ($jobs->have_posts()) : $jobs->the_post(); ?>
	<div class="job-listing">
		<div class="job-header">
			<?php
                $company_logo = get_post_meta(get_the_ID(), '_company_logo', true);
	    if (!empty($company_logo)) : ?>
			<div class="job-logo">
				<?php echo wp_get_attachment_image($company_logo, 'thumbnail'); ?>
			</div>
			<?php endif; ?>

			<div class="job-title-company">
				<h3 class="job-title">
					<a
						href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
				</h3>
				<p class="job-company">
					<?php echo esc_html(get_post_meta(get_the_ID(), '_company_name', true)); ?>
				</p>
			</div>
			<div class="job-date">
				<span><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago'; ?></span>
			</div>
		</div>
		<div class="job-details">
			<div class="job-meta">
				<?php
	            $worldwide = get_post_meta(get_the_ID(), '_worldwide', true);
	    $location = get_post_meta(get_the_ID(), '_job_location', true);
	    if ($worldwide === 'yes' || $location): ?>
				<span class="job-location">
					<svg class="location-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
						<g>
							<path
								d="M256,32c-74,0-134.2,58.7-134.2,132.7c0,16.4,3.5,34.3,9.8,50.4l-0.1,0l0.6,1.2l0,0c0.5,1.1,1,2.2,1.5,3.3L256,480   l121.8-259.1l0.6-1.2c0.5-1.1,1.1-2.2,1.6-3.4l0.4-1.1c6.5-16.1,9.8-33.1,9.8-50.3C390.2,90.7,330,32,256,32z M365.1,209.4   l-0.2,0.5c-0.3,0.6-0.6,1.3-0.9,1.9l-1,2.1L256,441.3L148.9,213.9l-0.9-2c-0.3-0.6-0.6-1.2-0.8-1.8c-5.9-14.5-9.1-30.6-9.1-45.4   c0-65,52.9-116.5,118-116.5s118,51.4,118,116.5C374,179.9,371,194.9,365.1,209.4z" />
							<path
								d="M256,96c-35.3,0-64,28.7-64,64s28.7,64,64,64s64-28.7,64-64S291.3,96,256,96z M256,206.9c-25.9,0-46.9-21-46.9-46.9   c0-25.9,21-46.9,46.9-46.9c25.9,0,46.9,21,46.9,46.9C302.9,185.9,281.9,206.9,256,206.9z" />
						</g>
					</svg>
					<?php echo $worldwide === 'yes' ? 'Worldwide' : esc_html($location); ?>
				</span>
				<?php endif; ?>

				<?php
	            $employment_type = get_the_terms(get_the_ID(), 'employment_type');
	    if ($employment_type): ?>
				<span class="job-type">
					<i class="fas fa-clock"></i>
					<?php echo esc_html($employment_type[0]->name); ?>
				</span>
				<?php endif; ?>
				<?php
	            $salary_range = get_the_terms(get_the_ID(), 'salary_range');
	    if ($salary_range) : ?>
				<span class="job-salary">
					<i class="fas fa-dollar-sign"></i>
					<?php echo esc_html($salary_range[0]->name); ?>
				</span>
				<?php endif; ?>
			</div>
			<div class="job-tags">
				<?php
	    $categories = get_the_terms(get_the_ID(), 'job_category') ?: array();
	    $skills = get_the_terms(get_the_ID(), 'job_skills') ?: array();
	    $tags = array_merge($categories, $skills);

	    foreach ($tags as $tag) :
	        $taxonomy_link = get_term_link($tag);
	        if (!is_wp_error($taxonomy_link)) :
	            ?>
				<a href="<?php echo esc_url($taxonomy_link); ?>"
					class="job-tag">
					<?php echo esc_html($tag->name); ?>
				</a>
				<?php
	        endif;
	    endforeach;
	    ?>
			</div>
		</div>
	</div>
	<?php endwhile; ?>
	<?php wp_reset_postdata(); ?>
	<?php else : ?>
	<p><?php _e('No jobs found', 'wp-remote-jobs'); ?>
	</p>
	<?php endif; ?>
</div>
<?php
    return ob_get_clean();
    wp_send_json_success($output);
}


function register_job_listings_block()
{
    register_block_type(__DIR__ . '/build', array(
        'render_callback' => 'render_job_listings_block',
    ));
}
add_action('init', 'register_job_listings_block');
function enqueue_job_filter_script()
{
    wp_enqueue_script('job-filter', plugin_dir_url(__FILE__) . '/src/view.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'enqueue_job_filter_script');

function filter_jobs_ajax()
{
    $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $location = isset($_POST['location']) ? sanitize_text_field($_POST['location']) : '';

    $tax_query = array('relation' => 'AND');

    if ($category) {
        $tax_query[] = array(
            'taxonomy' => 'job_category',
            'field' => 'slug',
            'terms' => $category,
        );
    }

    if ($type) {
        $tax_query[] = array(
            'taxonomy' => 'employment_type',
            'field' => 'slug',
            'terms' => $type,
        );
    }

    if ($location) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field' => 'slug',
            'terms' => $location,
        );
    }

    $args = array(
        'post_type' => 'jobs',
        'posts_per_page' => -1,
        'tax_query' => $tax_query,
    );

    // Add search criteria if provided
    if (!empty($search)) {
        $args['s'] = $search;
    }

    $query = new WP_Query($args);

    ob_start();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            ?>
<div class="job-listing">
	<div class="job-header">
		<div class="job-logo">
			<?php
                        $company_logo = get_post_meta(get_the_ID(), '_company_logo', true);
            if (!empty($company_logo)) : ?>
			<div class="job-logo">
				<?php echo wp_get_attachment_image($company_logo, 'thumbnail'); ?>
			</div>
			<?php endif; ?>
		</div>
		<div class="job-title-company">
			<h3 class="job-title"><?php the_title(); ?></h3>
			<p class="job-company">
				<?php echo esc_html(get_post_meta(get_the_ID(), '_company_name', true)); ?>
			</p>
		</div>
		<div class="job-date">
			<span><?php echo human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ago'; ?></span>
		</div>
	</div>
	<div class="job-details">
		<div class="job-meta">
			<span class="job-location">
				<svg class="location-icon" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">
					<g>
						<path
							d="M256,32c-74,0-134.2,58.7-134.2,132.7c0,16.4,3.5,34.3,9.8,50.4l-0.1,0l0.6,1.2l0,0c0.5,1.1,1,2.2,1.5,3.3L256,480   l121.8-259.1l0.6-1.2c0.5-1.1,1.1-2.2,1.6-3.4l0.4-1.1c6.5-16.1,9.8-33.1,9.8-50.3C390.2,90.7,330,32,256,32z M365.1,209.4   l-0.2,0.5c-0.3,0.6-0.6,1.3-0.9,1.9l-1,2.1L256,441.3L148.9,213.9l-0.9-2c-0.3-0.6-0.6-1.2-0.8-1.8c-5.9-14.5-9.1-30.6-9.1-45.4   c0-65,52.9-116.5,118-116.5s118,51.4,118,116.5C374,179.9,371,194.9,365.1,209.4z" />
						<path
							d="M256,96c-35.3,0-64,28.7-64,64s28.7,64,64,64s64-28.7,64-64S291.3,96,256,96z M256,206.9c-25.9,0-46.9-21-46.9-46.9   c0-25.9,21-46.9,46.9-46.9c25.9,0,46.9,21,46.9,46.9C302.9,185.9,281.9,206.9,256,206.9z" />
					</g>
				</svg>
				<?php
                $worldwide = get_post_meta(get_the_ID(), '_worldwide', true);
            $location = get_post_meta(get_the_ID(), '_job_location', true);
            echo $worldwide === 'yes' ? 'Worldwide' : ($location ? esc_html($location) : 'Remote');
            ?>
			</span>
			<span class="job-department">
				<i class="fas fa-users"></i>
				<?php echo esc_html(get_post_meta(get_the_ID(), '_department', true)) ?: 'N/A'; ?>
			</span>
			<span class="job-type">
				<i class="fas fa-clock"></i>
				<?php
            $employment_type = get_the_terms(get_the_ID(), 'employment_type');
            echo $employment_type ? esc_html($employment_type[0]->name) : 'N/A';
            ?>
			</span>
			<?php
                        $salary_range = get_the_terms(get_the_ID(), 'salary_range');
            if ($salary_range) : ?>
			<span class="job-salary">
				<i class="fas fa-dollar-sign"></i>
				<?php echo esc_html($salary_range[0]->name); ?>
			</span>
			<?php endif; ?>
		</div>
		<div class="job-tags">
			<?php
            $categories = get_the_terms(get_the_ID(), 'job_category') ?: array();
            $skills = get_the_terms(get_the_ID(), 'job_skills') ?: array();
            $tags = array_merge($categories, $skills);

            foreach ($tags as $tag) :
                $taxonomy_link = get_term_link($tag);
                if (!is_wp_error($taxonomy_link)) :
                    ?>
			<a href="<?php echo esc_url($taxonomy_link); ?>"
				class="job-tag">
				<?php echo esc_html($tag->name); ?>
			</a>
			<?php
                endif;
            endforeach;
            ?>
		</div>
	</div>
</div>
<?php
        }
    } else {
        echo '<p>No jobs found</p>';
    }
    wp_reset_postdata();

    $output = ob_get_clean();
    wp_send_json_success($output);
}
add_action('wp_ajax_filter_jobs', 'filter_jobs_ajax');
add_action('wp_ajax_nopriv_filter_jobs', 'filter_jobs_ajax');
?>