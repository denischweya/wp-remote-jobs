=== Remote Jobs ===
Contributors: Afrothemes, Fortisthemes
Tags: jobs, remote jobs, job board, recruitment
Requires at least: 5.8
Tested up to: 7.2
Stable tag: 1.0.8
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Build a remote job board on WordPress with 4 Gutenberg blocks: a filterable job list, a job detail sidebar, a job submission form, and a company registration form.

== Description ==

Remote Jobs turns a WordPress site into a job board using a custom "Job" post type and four Gutenberg blocks:

**Key Features:**

* **Job List Block:** Displays job postings in a grid or list layout, with search and dropdown filters for category, location, and skills. Filtering runs over AJAX, and category/location/skills/employment-type archive pages are supported out of the box via custom taxonomies.
* **Job Sidebar Block:** Shown on a single job's page, it displays company, salary range, location, employment type, application deadline, required skills, and benefits, plus an Apply Now button and a link to the company's website.
* **Submit Job Block:** A front-end form for logged-in users with the `publish_posts` capability to submit a job listing. New submissions are saved as `pending` so a site admin can review them before they go live.
* **Company Registration Block:** A front-end sign-up form for employers. Submitting it creates a new WordPress user account (with the `author` role, so the account can use the Submit Job block) and emails the new user their login details.

Perfect for:
* Career websites
* Company recruitment pages
* Niche job boards
* Remote work communities
* Professional associations

All four blocks work with any block theme. A companion "Remote Jobs" theme is also available, with templates and styling tuned specifically for this plugin's blocks.

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

= 1.0.8 =
* Fixed: company name was never displayed on job cards (only a one-letter logo placeholder); job-list.php now reads the existing `_remjobs_company` meta it was already populated with.
* Fixed: remote location was duplicated in job cards and list rows when both meta and taxonomy data were present.
* Fixed: the job list block's view.js was being enqueued twice under different handles, double-firing a DOMContentLoaded listener and duplicating an unstyled "star" indicator on every premium card; removed the redundant enqueue and the dead indicator code.
* Fixed: a `padding: 20rem` typo in the Submit Job block's stylesheet (meant to be `2rem`).
* Fixed: company registration created `subscriber` accounts, which lack the `publish_posts` capability the Submit Job block requires, so a new employer could never actually submit a job after registering. Accounts are now created with the `author` role.
* Added: Submit Job block now also collects and validates company name and remote location.
* Added: Job Sidebar block now also shows location, employment type, application deadline, required skills, and benefits (taxonomy data that existed but was not displayed).
* Added: job cards show an individually-tagged skill list and a real "Premium" badge (icon + label) instead of a CSS-only ribbon, and a human-readable "posted X ago" time.
* Removed: two orphaned, unreferenced `block.php` files (in `job-list/` and `submit-job/`) left over from an earlier refactor; one redeclared a function already defined in the active file, a latent fatal-error risk if ever accidentally included.
* Security: added the standard "if this file is called directly, abort" guard to several class and template-partial files that were missing it.

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