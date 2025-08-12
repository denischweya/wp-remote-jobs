<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/denischweya/wp-remote-jobs
 * @since             1.0.0
 * @package           Remote_Jobs
 *
 * @wordpress-plugin
 * Plugin Name:       Remote Jobs
 * Plugin URI:        https://github.com/denischweya/wp-remote-jobs
 * Description:       A simple job board plugin for WordPress
 * Version:           1.0.6
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Afrothemes
 * Author URI:        https://github.com/denischweya/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       remote-jobs
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('REMJOBS_VERSION', '1.0.0');
define('REMJOBS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('REMJOBS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-remjobs-activator.php
 */
function remjobs_activate()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-remjobs-activator.php';
    Remjobs_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-remjobs-deactivator.php
 */
function remjobs_deactivate()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-remjobs-deactivator.php';
    Remjobs_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'remjobs_activate');
register_deactivation_hook(__FILE__, 'remjobs_deactivate');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-remjobs-core.php';
// Include blocks registration file
require_once plugin_dir_path(__FILE__) . 'includes/blocks/blocks.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function remjobs_init()
{
    $plugin = new Remjobs_Core();
    $plugin->run();
}
add_action('plugins_loaded', 'remjobs_init');
