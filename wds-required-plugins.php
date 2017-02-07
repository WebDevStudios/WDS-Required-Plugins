<?php
/**
 * Plugin Name: WDS Required Plugins
 * Plugin URI: http://webdevstudios.com
 * Description: Forcefully require specific plugins to be activated.
 * Author: WebDevStudios
 * Author URI: http://webdevstudios.com
 * Version: 0.1.5
 * Domain: wds-required-plugins
 * License: GPLv2
 * Path: languages
 *
 * @package WDS_Required_Plugins
 */

// Exit if accessed directly.
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
	 * Whether text-domain has been registered.
	 *
	 * @var boolean
	 */
	private static $l10n_done = false;

	/**
	 * Text/markup for required text.
	 *
	 * @var string
	 */
	private $required_text = '';

	/**
	 * Creates or returns an instance of this class.
	 *
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

		// Attempt activation + load text domain in the admin.
		add_filter( 'admin_init', array( $this, 'activate_if_not' ) );
		add_filter( 'admin_init', array( $this, 'required_text_markup' ) );

		// Filter plugin links to remove deactivate option.
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );

		// Remove plugins from the plugins.
		add_filter( 'all_plugins', array( $this, 'maybe_remove_plugins_from_list' ) );

		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'l10n' ) );
	}

	/**
	 * Activate required plugins if they are not.
	 *
	 * @since 0.1.1
	 */
	public function activate_if_not() {

		// Bail on ajax requests.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Bail if we're not in the admin.
		if ( ! is_admin() ) {
			return;
		}

		// Don't do anything if the user isn't permitted, or its an ajax request.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Loop through each plugin we have set as required.
		foreach ( $this->get_required_plugins() as $plugin ) {
			$this->maybe_activate_plugin( $plugin );
		}

		// If we're multisite, attempt to network activate our plugins.
		if ( is_multisite() && function_exists( 'is_plugin_active_for_network' ) ) {

			// Loop through each network required plugin.
			foreach ( $this->get_network_required_plugins() as $plugin ) {
				$this->maybe_activate_plugin( $plugin, true );
			}
		}
	}

	/**
	 * Activates a required plugin if it's found, and auto-activation is enabled.
	 *
	 * @since  0.1.4
	 *
	 * @param  string  $plugin  The plugin to activate.
	 * @param  boolean $network Whether we are activating a network-required plugin.
	 *
	 * @return WP_Error|null    WP_Error on invalid file or null on success.
	 */
	public function maybe_activate_plugin( $plugin, $network = false ) {

		// Don't activate if already active.
		if ( is_plugin_active( $plugin ) ) {
			return;
		}

		// Don't activate if already network-active.
		if ( $network && is_plugin_active_for_network( $plugin ) ) {
			return;
		}

		// Filter if you don't want the required plugin to auto-activate. `true` by default.
		if ( ! apply_filters( 'wds_required_plugin_auto_activate', true, $plugin, $network ) ) {
			return;
		}

		// Filter if you don't want the required plugin to network-activate by default.
		$network_wide = $network ? true : apply_filters( 'wds_required_plugin_network_activate', is_multisite(), $plugin, $network );

		// Activate the plugin.
		$result = activate_plugin( $plugin, null, $network_wide );

		// If we activated correctly, than return results of that.
		if ( ! is_wp_error( $result ) ) {
			return $result;
		}

		// If auto-activation failed, and there is an error, log it.
		if ( apply_filters( 'wds_required_plugin_log_if_not_found', true, $plugin, $result, $network ) ) {

			// Set default log text.
			$default_log_text = __( 'Required Plugin auto-activation failed for: %1$s, with message: %2$s', 'wds-required-plugins' );

			// Filter the logging message format/text.
			$log_msg_format = apply_filters( 'wds_required_plugins_error_log_text', $default_log_text, $plugin, $result, $network );

			// Get our error message.
			$error_message = method_exists( $result, 'get_error_message' ) ? $result->get_error_message() : '';

			// Trigger our error, with all our log messages.
			trigger_error( sprintf( esc_attr( $log_msg_format ), esc_attr( $plugin ), esc_attr( $error_message ) ) );
		}

		return $result;
	}

	/**
	 * The required plugin label text.
	 *
	 * @since  0.1.0
	 *
	 * @return  void
	 */
	public function required_text_markup() {
		$this->required_text = apply_filters( 'wds_required_plugins_text', sprintf( '<span style="color: #888">%s</span>', __( 'WDS Required Plugin', 'wds-required-plugins' ) ) );
	}

	/**
	 * Remove the deactivation link for all custom/required plugins
	 *
	 * @since 0.1.0
	 *
	 * @param array  $actions  Array of actions avaible.
	 * @param string $plugin   Slug of plugin.
	 *
	 * @return array
	 */
	public function filter_plugin_links( $actions = array(), $plugin ) {

		// Get our required plugins for network + normal.
		$required_plugins = array_unique( array_merge( $this->get_required_plugins(), $this->get_network_required_plugins() ) );

		// Remove deactivate link for required plugins.
		if ( array_key_exists( 'deactivate', $actions ) && in_array( $plugin, $required_plugins, true ) ) {

			// Filter if you don't want the required plugin to be network-required by default.
			if ( ! is_multisite() || apply_filters( 'wds_required_plugin_network_activate', true, $plugin ) ) {
				$actions['deactivate'] = $this->required_text;
			}
		}

		return $actions;
	}

	/**
	 * Remove required plugins from the plugins list, if enabled.
	 *
	 * @since   0.1.5
	 *
	 * @param   array $plugins Array of plugins.
	 *
	 * @return  array          Array of plugins.
	 */
	public function maybe_remove_plugins_from_list( $plugins ) {

		// Allow for removing all plugins from the plugins list.
		if ( ! apply_filters( 'wds_required_plugin_remove_from_list', false ) ) {
			return $plugins;
		}

		// Loop through each of our required plugins.
		foreach ( $this->get_required_plugins() as $required_plugin ) {

			// Remove from the all plugins list.
			unset( $plugins[ $required_plugin ] );
		}

		// Send it back.
		return $plugins;
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
	 *
	 * @since  0.1.1
	 *
	 * @return  void
	 */
	public function l10n() {

		// Only do this one time.
		if ( self::$l10n_done ) {
			return;
		}

		// Bail on ajax requests.
		if ( wp_doing_ajax() ) {
			return;
		}

		// Bail if we're not in the admin.
		if ( ! is_admin() ) {
			return;
		}

		// Don't do anything if the user isn't permitted, or its an ajax request.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Try to load mu-plugin textdomain.
		if ( load_muplugin_textdomain( 'wds-required-plugins', '/languages/' ) ) {
			self::$l10n_done = true;
			return;
		}

		// If we didn't load, load as a plugin.
		if ( load_plugin_textdomain( 'wds-required-plugins', false, '/languages/' ) ) {
			self::$l10n_done = true;
			return;
		}

		// If we didn't load yet, load as a theme.
		if ( load_theme_textdomain( 'wds-required-plugins', '/languages/' ) ) {
			self::$l10n_done = true;
			return;
		}

		// If we still didn't load, assume our text domain is right where we are.
		$locale = apply_filters( 'plugin_locale', get_locale(), 'wds-required-plugins' );
		$mofile = dirname( __FILE__ ) . '/languages/wds-required-plugins-' . $locale . '.mo';
		load_textdomain( 'wds-required-plugins', $mofile );
		self::$l10n_done = true;
	}
}

WDS_Required_Plugins::init();
