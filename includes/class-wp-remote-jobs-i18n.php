<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://https://denis.swishfolio.com/
 * @since      1.0.0
 *
 * @package    Wp_Remote_Jobs
 * @subpackage Wp_Remote_Jobs/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Wp_Remote_Jobs
 * @subpackage Wp_Remote_Jobs/includes
 * @author     Denis Bosire <denischweya@gmail.com>
 */
class Wp_Remote_Jobs_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'wp-remote-jobs',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
