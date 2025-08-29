=== Remote Jobs ===
Contributors: Afrothemes, Fortisthemes
Tags: jobs, remote jobs, job board, recruitment
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 1.0.7
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create a powerful Remote Jobs on your WordPress site with 4 customizable Gutenberg blocks for job listings, search, categories, and application forms.

== Description ==

Transform your WordPress website into a fully-featured Remote Jobs with WP Remote Jobs. This plugin provides four essential Gutenberg blocks designed to create a seamless job listing and application experience:

**Key Features:**

* **Jobs Grid Block:** Display job listings in a responsive grid layout with filtering options for categories, experience levels, and job types
* **Job Search Block:** Advanced search functionality with auto-complete and filters for location, salary range, and employment type
* **Job Categories Block:** Showcase job categories in an intuitive interface with category icons and post counts
* **Job Application Block:** Custom application form with resume upload, cover letter, and automated email notifications

Perfect for:
* Career websites
* Company recruitment pages
* Niche job boards
* Remote work communities
* Professional associations

The plugin integrates seamlessly with WordPress's block editor while maintaining excellent performance and SEO optimization out of the box.

== Installation ==

1. Upload the `remote-jobs` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Start creating job listings by going to Jobs > Add New in your admin dashboard
4. Use the Gutenberg blocks to display jobs on your pages and posts

== Frequently Asked Questions ==

= How do I add job listings? =

After activating the plugin, you'll see a new "Jobs" menu in your WordPress admin dashboard. Click "Add New" to create job listings with all the necessary fields like job title, description, company, location, and more.

= Can I customize the job listing layout? =

Yes! The plugin includes customizable Gutenberg blocks that allow you to control how jobs are displayed on your site. You can choose between grid and list layouts, enable/disable filters, and customize colors and styling.

= Does this plugin work with my theme? =

The Remote Jobs plugin is designed to work with any WordPress theme that supports Gutenberg blocks. The blocks use semantic HTML and modern CSS that adapts to most theme styles.

= Is the plugin GDPR compliant? =

The plugin follows WordPress best practices for data handling. For job applications, ensure you add appropriate privacy notices and obtain user consent as required by GDPR and other privacy regulations.

== Changelog ==

= 1.0.7 =
* Fixed WordPress.org guidelines violation: Replace hardcoded wp-admin/admin-ajax.php URLs with proper admin_url() function
* Improved AJAX endpoint compatibility for different WordPress configurations
* Enhanced security by removing static fallback paths and enforcing proper wp_localize_script usage

= 1.0.6 =
* Enhanced job list block with improved input sanitization and better CSS contrast
* Updated Ajax endpoints for better security and compatibility
* Improved responsive layout and color scheme consistency

= 1.0.5 =
* Updated job list block styles and enhanced layout detection
* Improved color scheme and SCSS structure for maintainability

= 1.0.0 =
* Initial release
* Job listings custom post type with meta fields
* Gutenberg blocks for displaying job listings
* Ajax-powered filtering and search functionality
* Responsive grid and list layouts