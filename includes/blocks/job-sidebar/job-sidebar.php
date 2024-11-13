<?php
/**
 * Plugin Name:       Job Side Bar
 * Description:       Example block scaffolded with Create Block tool.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           0.1.0
 * Author:            The WordPress Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       job-side-bar
 *
 * @package WpRemoteJobs
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function wp_remote_jobs_job_side_bar_block_init()
{
    register_block_type(__DIR__ . '/build', array(
        'render_callback' => 'render_job_sidebar_block'
    ));
}
add_action('init', 'wp_remote_jobs_job_side_bar_block_init');

function render_job_sidebar_block($attributes, $content, $block)
{
    // Get current post
    $post = get_post();
    if (!$post || $post->post_type !== 'jobs') {
        return '';
    }

    // Get author data
    $author_id = $post->post_author;
    $company_logo = get_user_meta($author_id, 'company_logo', true);
    $company_name = get_user_meta($author_id, 'company_name', true);
    $company_url = get_user_meta($author_id, 'company_website', true);

    // Get taxonomies
    $location = get_the_terms($post->ID, 'job_location');
    $employment_type = get_the_terms($post->ID, 'job_type');
    $salary_range = get_the_terms($post->ID, 'salary_range');
    $job_category = get_the_terms($post->ID, 'job_category');
    $skills = get_the_terms($post->ID, 'job_skills');

    // Get application link
    $application_link = get_post_meta($post->ID, '_application_link', true);

    // Define allowed SVG tags and attributes for wp_kses
    $allowed_svg = [
        'svg' => [
            'class' => true,
            'viewBox' => true,
            'fill' => true,
            'xmlns' => true,
        ],
        'path' => [
            'd' => true,
            'fill' => true,
        ],
    ];

    // SVG Icons - Store them in variables with proper escaping
    $location_icon = wp_kses(
        '<svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" fill="currentColor"/></svg>',
        $allowed_svg
    );

    $employment_icon = wp_kses(
        '<svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M20 6h-4V4c0-1.11-.89-2-2-2h-4c-1.11 0-2 .89-2 2v2H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-6 0h-4V4h4v2z" fill="currentColor"/></svg>',
        $allowed_svg
    );

    $salary_icon = wp_kses(
        '<svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="currentColor"/></svg>',
        $allowed_svg
    );

    $category_icon = wp_kses(
        '<svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 2l-5.5 9h11L12 2zm0 3.84L13.93 9h-3.87L12 5.84zM17.5 13c-2.49 0-4.5 2.01-4.5 4.5s2.01 4.5 4.5 4.5 4.5-2.01 4.5-4.5-2.01-4.5-4.5-4.5zm0 7c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5zM3 21.5h8v-8H3v8zm2-6h4v4H5v-4z" fill="currentColor"/></svg>',
        $allowed_svg
    );

    ob_start();
    ?>
<div <?php echo esc_attr(get_block_wrapper_attributes(['class' => 'job-sidebar'])); ?>>
	<!-- Company Header -->
	<div class="company-header">
		<?php if ($company_logo): ?>
		<img src="<?php echo esc_url($company_logo); ?>"
			alt="<?php echo esc_attr($company_name); ?>"
			class="company-logo" />
		<?php endif; ?>
		<?php if ($company_name): ?>
		<h3 class="company-name">
			<?php echo esc_html($company_name); ?>
		</h3>
		<?php endif; ?>
	</div>

	<!-- Job Details -->
	<div class="job-details">
		<?php if ($location): ?>
		<div class="detail-item">
			<?php echo wp_kses($location_icon, $allowed_svg); ?>
			<div>
				<strong><?php esc_html_e('Location', 'remote-jobs'); ?></strong>
				<p><?php echo esc_html($location[0]->name); ?></p>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($employment_type): ?>
		<div class="detail-item">
			<?php echo wp_kses($employment_icon, $allowed_svg); ?>
			<div>
				<strong><?php esc_html_e('Employment Type', 'remote-jobs'); ?></strong>
				<p><?php //echo esc_html($employment_type[0]->name);?>
				</p>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($salary_range): ?>
		<div class="detail-item">
			<?php echo wp_kses($salary_icon, $allowed_svg); ?>
			<div>
				<strong><?php esc_html_e('Salary Range', 'remote-jobs'); ?></strong>
				<p><?php echo esc_html($salary_range[0]->name); ?>
				</p>
			</div>
		</div>
		<?php endif; ?>

		<?php if ($job_category): ?>
		<div class="detail-item">
			<?php echo wp_kses($category_icon, $allowed_svg); ?>
			<div>
				<strong><?php esc_html_e('Category', 'remote-jobs'); ?></strong>
				<p><?php echo esc_html($job_category[0]->name); ?>
				</p>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php if ($skills): ?>
	<div class="skills-section">
		<strong>Skills</strong>
		<div class="skills-pills">
			<?php foreach ($skills as $skill): ?>
			<span class="skill-pill">
				<?php echo esc_html($skill->name); ?>
			</span>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($company_url): ?>
	<a href="<?php echo esc_url($company_url); ?>"
		class="company-website" target="_blank" rel="noopener noreferrer">
		Visit Company Website
	</a>
	<?php endif; ?>

	<?php if ($application_link): ?>
	<a href="<?php echo esc_url($application_link); ?>"
		class="apply-button">
		Apply Now
	</a>
	<?php endif; ?>
</div>
<?php
    return ob_get_clean();
}

function enqueue_block_assets()
{
    $asset_file = include(plugin_dir_path(__FILE__) . 'build/index.asset.php');

    // Enqueue front-end and editor styles
    wp_enqueue_style(
        'job-sidebar-style',
        plugins_url('build/style-index.css', __FILE__),
        [],
        $asset_file['version']
    );
}
add_action('init', 'enqueue_block_assets');
?>