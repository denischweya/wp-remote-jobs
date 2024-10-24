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
			<div class="job-logo">
				<?php
                        $company_logo = get_post_meta(get_the_ID(), '_company_logo', true);
	    if ($company_logo) {
	        echo wp_get_attachment_image($company_logo, 'thumbnail');
	    } else {
	        echo '<div class="default-logo"></div>';
	    }
	    ?>
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
					<i class="fas fa-map-marker-alt"></i>
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
	    $tags = array_merge(
	        get_the_terms(get_the_ID(), 'job_category') ?: array(),
	        get_the_terms(get_the_ID(), 'job_skills') ?: array()
	    );
	    foreach ($tags as $tag) :
	        ?>
				<span
					class="job-tag"><?php echo esc_html($tag->name); ?></span>
				<?php endforeach; ?>
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
            if ($company_logo) {
                echo wp_get_attachment_image($company_logo, 'thumbnail');
            } else {
                echo '<div class="default-logo"></div>';
            }
            ?>
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
				<i class="fas fa-map-marker-alt"></i>
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
            $tags = array_merge(
                get_the_terms(get_the_ID(), 'job_category') ?: array(),
                get_the_terms(get_the_ID(), 'job_skills') ?: array()
            );
            foreach ($tags as $tag) :
                ?>
			<span
				class="job-tag"><?php echo esc_html($tag->name); ?></span>
			<?php endforeach; ?>
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