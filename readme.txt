=== Remote Jobs ===
Contributors: Afrothemes, Fortisthemes
Tags: job board, remote jobs, job listings, stripe, paypal
Requires at least: 5.8
Tested up to: 7.2
Stable tag: 1.1.4
Requires PHP: 7.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Build a remote job board with 4 Gutenberg blocks, AJAX filtering, and paid featured listings via Stripe and PayPal.

== Description ==

**Remote Jobs** turns any WordPress site into a full-featured remote job board. It adds a custom "Job" post type, four Gutenberg blocks, filterable taxonomy archives, and optional paid featured listings processed by **Stripe** and **PayPal** — no page builder or extra plugin required.

**[View the live demo](https://remotejobs.swishfolio.com/)** | **[Product page and downloads](https://denis.swishfolio.com/products/remote-jobs/)**

**Blocks:**

* **Job List Block:** Displays job postings in a grid or list layout, with search and dropdown filters for category, location, and skills. Filtering runs over AJAX, so visitors narrow results without a page reload. Category, location, skills, and employment-type archive pages are supported out of the box via custom taxonomies.
* **Job Sidebar Block:** Shown on a single job's page, it displays company, salary range, location, employment type, application deadline, required skills, and benefits, plus an Apply Now button and a link to the company's website.
* **Submit Job Block:** A front-end form for logged-in users with the `publish_posts` capability to submit a job listing. Free submissions are saved as `pending` so a site admin can review them before they go live.
* **Company Registration Block:** A front-end sign-up form for employers. Submitting it creates a new WordPress user account (with the `author` role, so the account can use the Submit Job block) and emails the new user their login details.

**Monetize listings with Stripe and PayPal:**

Employers can pay to feature a job alongside free submissions, which continue to work exactly as before:

* **Single plan:** a one-time payment features a job for a fixed number of days (30 by default), then it expires.
* **Monthly plan:** a recurring subscription (via Stripe Billing or PayPal Subscriptions) keeps a job featured for as long as it's active.
* Checkout is hosted by Stripe or PayPal, and payment is confirmed server-side by webhook — a paid job **auto-publishes** the moment payment is confirmed, skipping the moderation queue.
* Featured status **expires automatically** on schedule; an admin setting controls whether an expired listing is unpublished or simply un-featured.
* Pricing, currency, plan duration, and gateway API keys are all configurable from the plugin's settings — amounts are always resolved server-side, never trusted from the browser.

Perfect for:
* Career websites
* Company recruitment pages
* Niche job boards
* Remote work communities
* Professional associations

All four blocks work with any block theme. A companion "Remote Jobs" theme is also available, with templates and styling tuned specifically for this plugin's blocks.

== Installation ==

1. Upload the `remote-jobs` folder to the `/wp-content/plugins/` directory, or install it directly from the WordPress Plugin Directory via Plugins > Add New.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Start creating job listings by going to Jobs > Add New in your admin dashboard, or let employers submit them from the front end with the Submit Job block.
4. Add the Job List, Job Sidebar, Submit Job, and Company Registration blocks to your pages using the block editor.
5. (Optional) To accept paid featured listings, open the Jobs > Payments settings page and add your Stripe and/or PayPal API keys, then set your pricing and expiry preferences.

== Frequently Asked Questions ==

= How do I add job listings? =

After activating the plugin, you'll see a new "Jobs" menu in your WordPress admin dashboard. Click "Add New" to create job listings with all the necessary fields like job title, description, company, location, and more. Employers can also submit jobs themselves from the front end using the Submit Job block.

= Do I need Stripe or PayPal to use this plugin? =

No. Paid featured listings are entirely optional. Free job submissions work out of the box and go through normal admin moderation (saved as `pending` until approved). Stripe and PayPal are only needed if you want to offer employers a paid "featured" upgrade.

= How does a paid featured listing get published? =

Once an employer completes checkout, Stripe or PayPal sends a signed webhook confirming payment. The plugin verifies that webhook server-side and automatically publishes and features the job — there's no manual approval step for paid listings. Featured status then expires automatically based on the plan (a fixed number of days for the single plan, or the active billing period for the monthly subscription), and an admin setting decides whether an expired listing is unpublished or just loses its featured badge.

= What's the difference between the Single and Monthly plans? =

The Single plan is a one-time payment that features a job for a fixed number of days (30 by default). The Monthly plan is a recurring subscription through Stripe Billing or PayPal Subscriptions that keeps a job featured for as long as the subscription stays active, and stops featuring it if it's canceled or a renewal payment fails.

= Can I customize the job listing layout? =

Yes! The plugin includes customizable Gutenberg blocks that allow you to control how jobs are displayed on your site. You can choose between grid and list layouts, enable/disable filters, and customize colors and styling.

= Does this plugin work with my theme? =

The Remote Jobs plugin is designed to work with any WordPress block theme. The blocks use semantic HTML and modern CSS that adapts to most theme styles. A companion "Remote Jobs" theme is also available with templates tuned specifically for this plugin.

= Is the plugin GDPR compliant? =

The plugin follows WordPress best practices for data handling. For job applications and payments, ensure you add appropriate privacy notices and obtain user consent as required by GDPR and other privacy regulations.

== Screenshots ==

1. Job List block showing a filterable grid of job postings with category, location, and skills dropdowns.
2. A single job page using the Job Sidebar block, showing company details, salary, deadline, and an Apply Now button.
3. The front-end Submit Job form, including the optional plan chooser for Free vs. Featured (Single/Monthly) listings.
4. The Company Registration form for employers signing up.
5. The admin Payments settings screen for configuring Stripe/PayPal keys, pricing, and expiry behavior.
6. The admin Orders screen showing a log of job payment orders with plan, gateway, amount, and status.
7. The admin Jobs list showing published listings with category, skills, location, benefits, salary range, and featured status columns.

== Changelog ==

= 1.1.4 =
* New banner on the plugin directory listing.
* New briefcase icon for the Jobs admin menu.

= 1.1.3 =
* Added a link to the product page and downloads in the readme.

= 1.1.2 =
* Added a banner and icon to the plugin directory listing.
* The live demo link in the readme is now clickable.
* Removed a development settings file from the distributed plugin.

= 1.1.1 =
* Added plugin screenshots (blocks + admin) to the plugin directory listing.
* Added a live demo link and refreshed the readme.

= 1.1.0 =
* Added: paid featured listings. Employers can pay via Stripe or PayPal to feature a job, with a one-time Single plan (fixed-duration feature) and a recurring Monthly subscription plan (Stripe Billing / PayPal Subscriptions).
* Added: payment confirmation is handled via signed gateway webhooks; a paid job auto-publishes and becomes featured as soon as payment is confirmed, bypassing the normal moderation queue. Free submissions are unaffected and still go through standard `pending` moderation.
* Added: featured listings expire automatically on a schedule; a new admin setting controls whether an expired listing is unpublished or simply loses its featured status.
* Added: new Payments settings page (Jobs menu) for Stripe/PayPal API keys, sandbox/live mode, currency, plan pricing and duration, monthly scope (per-job or account-wide with an optional cap), and expiry behavior.

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

== Upgrade Notice ==

= 1.1.0 =
This release adds optional paid featured listings via Stripe and PayPal. No action is required to keep using free job submissions as before. To accept payments, configure your gateway keys and pricing on the new Jobs > Payments settings page after updating.
