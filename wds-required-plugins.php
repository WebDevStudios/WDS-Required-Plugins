<?php
/**
 * Plugin Name: WDS Required Plugins
 * Plugin URI: http://webdevstudios.com
 * Description: Forcefully require specific plugins to be activated.
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * Version: 0.1.3
 * Domain: wds-required-plugins
 * License: GPLv2
 * Path: languages
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
	 * Whether text-domain has been registered
	 * @var boolean
	 */
	private static $l10n_done = false;

	/**
	 * Text/markup for required text
	 * @var string
	 */
	private $required_text = '';

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
		add_filter( 'admin_init', array( $this, 'activate_if_not' ) );
		add_filter( 'admin_init', array( $this, 'required_text_markup' ) );

		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );

		// load text domain
		add_action( 'plugins_loaded', array( $this, 'l10n' ) );
	}

	/**
	 * Activate required plugins if they are not.
	 *
	 * @since 0.1.1
	 */
	public function activate_if_not() {
		foreach ( $this->get_required_plugins() as $plugin ) {
			if ( ! is_plugin_active( $plugin ) ) {
				// Filter if you don't want the required plugin to network-activate by default.
				activate_plugin( $plugin, null, apply_filters( 'wds_required_plugin_network_activate', is_multisite(), $plugin ) );
			}
		}

		if ( is_multisite() && is_admin() && function_exists( 'is_plugin_active_for_network' ) ) {
			foreach ( $this->get_network_required_plugins() as $plugin ) {
				if ( ! is_plugin_active_for_network( $plugin ) ) {
					activate_plugin( $plugin, null, true );
				}
			}
		}
	}

	public function required_text_markup() {
		$this->required_text = apply_filters( 'wds_required_plugins_text', sprintf( '<span style="color: #888">%s</span>', __( 'WDS Required Plugin', 'wds-required-plugins' ) ) );
	}

	/**
	 * Remove the deactivation link for all custom/required plugins
	 *
	 * @since 0.1.0
	 *
	 * @param $actions
	 * @param $plugin
	 * @param $plugin_data
	 * @param $context
	 *
	 * @return array
	 */
	public function filter_plugin_links( $actions = array(), $plugin ) {
		$required_plugins = array_unique( array_merge( $this->get_required_plugins(), $this->get_network_required_plugins() ) );
		// Remove deactivate link for required plugins
		if ( array_key_exists( 'deactivate', $actions ) && in_array( $plugin, $required_plugins ) ) {
			// Filter if you don't want the required plugin to be network-required by default.
			if ( ! is_multisite() || apply_filters( 'wds_required_plugin_network_activate', true, $plugin ) ) {
				$actions['deactivate'] = $this->required_text;
			}
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

	/**
	 * Get the network plugins that are required for the project. Plugins will be registered by the wds_network_required_plugins filter
	 *
	 * @since  0.1.3
	 *
	 * @return array
	 */
	public function get_network_required_plugins() {
		return (array) apply_filters( 'wds_network_required_plugins', array() );
	}

	/**
	 * Load this library's text domain
	 * @since  0.2.1
	 */
	public function l10n() {
		// Only do this one time
		if ( self::$l10n_done ) {
			return;
		}

		$loaded = load_plugin_textdomain( 'wds-required-plugins', false, '/languages/' );
		if ( ! $loaded ) {
			$loaded = load_muplugin_textdomain( 'wds-required-plugins', '/languages/' );
		}
		if ( ! $loaded ) {
			$loaded = load_theme_textdomain( 'wds-required-plugins', '/languages/' );
		}

		if ( ! $loaded ) {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'wds-required-plugins' );
			$mofile = dirname( __FILE__ ) . '/languages/wds-required-plugins-'. $locale .'.mo';
			load_textdomain( 'wds-required-plugins', $mofile );
		}
	}

}

WDS_Required_Plugins::init();
