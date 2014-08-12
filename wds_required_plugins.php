<?php
/*
Plugin Name: WDS Required Plugins
Plugin URI: http://webdevstudios.com
Description: Make certain plugins required so that they cannot be (easily) deactivated.
Author: WebDevStudios
Author URI: http://webdevstudios.com
Version: 0.1.0
License: GPLv2
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required plugins class
 *
 * @package WordPress
 *
 * @subpackage Project
 */
class WDS_Required_Plugins {

	/**
	 * Instance of this class.
	 *
	 * @var WDS_Required_Plugins object
	 */
	public static $instance = null;

	/**
	 * Creates or returns an instance of this class.
	 * @since  0.1.0
	 * @return WDS_Required_Plugins A single instance of this class.
	 */
	public static function init() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Initiate our hooks
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );
	}

	/**
	 * Remove the deactivation link for all custom/required plugins
	 *
	 * @since 0.1.0
	 *
	 * @param $actions
	 * @param $plugin_file
	 * @param $plugin_data
	 * @param $context
	 *
	 * @return array
	 */
	public function filter_plugin_links( $actions = array(), $plugin_file ) {
		// Remove edit link for all plugins
		if ( array_key_exists( 'edit', $actions ) ) {
			unset( $actions['edit'] );
		}

		// Remove deactivate link for required plugins
		if( array_key_exists( 'deactivate', $actions ) && in_array( $plugin_file, $this->get_required_plugins() ) ) {
			$actions['deactivate'] = sprintf( '<span style="color: #888">%s</span>', __( 'WDS Required Plugin', '_s' ) );
		}

		return $actions;
	}


	/**
	 * Get the plugins that are required for the project. Plugins will be registered by the wds_required_plugins filter
	 *
	 * @since  0.1.0
	 *
	 * @return array
	 */
	public function get_required_plugins() {
		return (array) apply_filters( 'wds_required_plugins', array() );
	}

}
WDS_Required_Plugins::init();
