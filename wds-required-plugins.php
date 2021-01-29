<?php // @codingStandardsIgnoreLine: Filename okay here.
/**
 * Plugin Name: WDS Required Plugins
 * Plugin URI:  http://webdevstudios.com
 * Description: Forcefully require specific plugins to be activated.
 * Author:      WebDevStudios
 * Author URI:  http://webdevstudios.com
 * Version:     1.2.1
 * Domain:      wds-required-plugins
 * License:     GPLv2
 * Path:        languages
 * Props:       1.0.0 - Patrick Garman, Justin Sternberg, Brad Parbs
 *
 * @package     WDS_Required_Plugins
 * @since       0.1.4
 *
 * Required:    true
 */

namespace WebDevStudios\Required_Plugins;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'src/WP/CLI.php';

/**
 * Required plugins class
 *
 * @package WordPress
 *
 * @subpackage Project
 * @since      Unknown
 */
class Plugin {

	/**
	 * Instance of this class.
	 *
	 * @author Justin Sternberg
	 * @since Unknown
	 *
	 * @var Plugin object
	 */
	public static $instance = null;

	/**
	 * Whether text-domain has been registered.
	 *
	 * @var boolean
	 *
	 * @author Justin Sternberg
	 * @since  Unknown
	 */
	private static $l10n_done = false;

	/**
	 * Text/markup for required text.
	 *
	 * @see  self::required_text_markup() This will set the default value, but we
	 *                                    can't here because we want to translate it.
	 *
	 * @var string
	 *
	 * @author Justin Sternberg
	 * @since  Unknown
	 */
	private $required_text = '';

	/**
	 * Required Text Code.
	 *
	 * @author Aubrey Portwood
	 * @since  1.0.0
	 *
	 * @var string
	 */
	private $required_text_code = '<span style="color: #888">%s</span>';

	/**
	 * Logged incompatibilities.
	 *
	 * @author Aubrey Portwood
	 * @since  1.0.0
	 *
	 * @var array
	 */
	public $incompatibilities = array();

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  0.1.0
	 * @author Justin Sternberg
	 *
	 * @return Plugin A single instance of this class.
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
	 * @author  Unknown
	 *
	 * @return void
	 */
	private function __construct() {
		if ( $this->incompatible() ) {
			return;
		}

		// Attempt activation + load text domain in the admin.
		add_action( 'admin_init', array( $this, 'activate_if_not' ) );
		add_action( 'admin_init', array( $this, 'required_text_markup' ) );
		add_filter( 'extra_plugin_headers', array( $this, 'add_required_plugin_header' ) );

		// Filter plugin links to remove deactivate option.
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'filter_plugin_links' ), 10, 2 );

		// Remove plugins from the plugins.
		add_filter( 'all_plugins', array( $this, 'maybe_remove_plugins_from_list' ) );

		// Load text domain.
		add_action( 'plugins_loaded', array( $this, 'l10n' ) );
	}

	/**
	 * Are we currently incompatible with something?
	 *
	 * @author Aubrey Portwood
	 * @since  1.0.0
	 *
	 * @return boolean True if we are incompatible with something, false if not.
	 */
	public function incompatible() {

		// Our tests.
		$this->incompatibilities = array(

			/*
			 * WP Migrate DB Pro is performing an AJAX migration.
			 */
			(bool) $this->is_wpmdb(),
		);

		/**
		 * Add or filter your incompatibility tests here.
		 *
		 * Note, the entire array needs to be false for
		 * there to not be any incompatibilities.
		 *
		 * @author Aubrey Portwood
		 *
		 * @since 1.0.0
		 * @param array $incom A list of tests that determine incompatibilities.
		 */
		$filter = apply_filters( 'wds_required_plugins_incompatibilities', $this->incompatibilities );
		if ( is_array( $filter ) ) {

			// The filter might have added more tests, use those.
			$this->incompatibilities = $filter;
		}

		// If the array has any incompatibility, we are incompatible.
		return in_array( true, $this->incompatibilities, true );
	}

	/**
	 * Is WP Migrate DB Pro doing something?
	 *
	 * @author Aubrey Portwood
	 * @since  1.0.0
	 *
	 * @return boolean True if we find wpmdb set as the action.
	 */
	public function is_wpmdb() {

		// @codingStandardsIgnoreLine: Nonce validation not necessary here.
		return wp_doing_ajax() && stristr( isset( $_POST['action'] ) && is_string( $_POST['action'] ) ? $_POST['action'] : '', 'wpmdb_' );
	}

	/**
	 * Activate required plugins if they are not.
	 *
	 * @since 0.1.1
	 * @author Unknown
	 * @return void Early bails when we don't need to activate it.
	 */
	public function activate_if_not() {

		// If we're installing multisite, then disable our plugins and bail out.
		if ( defined( 'WP_INSTALLING_NETWORK' ) && WP_INSTALLING_NETWORK ) {
			add_filter( 'pre_option_active_plugins', '__return_empty_array' );
			add_filter( 'pre_site_option_active_sitewide_plugins', '__return_empty_array' );
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
	 * @author  Unknown
	 *
	 * @author Aubrey Portwood <aubrey@webdevstudios.com>
	 * @since 1.0.1 Added Exception if plugin not found.
	 *
	 * @param  string  $plugin  The plugin to activate.
	 * @param  boolean $network Whether we are activating a network-required plugin.
	 *
	 * @return void
	 *
	 * @throws Exception If we can't activate a required plugin.
	 */
	public function maybe_activate_plugin( $plugin, $network = false ) {

		/**
		 * Filter if you don't want the required plugin to auto-activate. `true` by default.
		 *
		 * If the plugin you are making required is not active, this will
		 * not force it to be activated.
		 *
		 * @author  Justin Sternberg
		 * @since   Unknown
		 *
		 * @param boolean $auto_activate Should we auto-activate the plugin, true by default.
		 * @param string  $plugin        The plugin being activated.
		 * @param string  $network       On what network?
		 */
		$auto_activate = apply_filters( 'wds_required_plugin_auto_activate', true, $plugin, $network );
		if ( ! $auto_activate ) {

			// Don't auto-activate.
			return;
		}

		/**
		 * Is this plugin supposed to be activated network wide?
		 *
		 * @author  Justin Sternberg
		 * @since   Unknown
		 *
		 * @param boolean $is_multisite The value of is_multisite().
		 * @param string  $plugin       The plugin being activated.
		 * @param string  $network      The network.
		 */
		$is_multisite = apply_filters( 'wds_required_plugin_network_activate', is_multisite(), $plugin, $network );

		// Filter if you don't want the required plugin to network-activate by default.
		$network_wide = $network ? true : $is_multisite;

		// Where is the plugin file?
		$abs_plugin = trailingslashit( WP_PLUGIN_DIR ) . $plugin;

		// Only if the plugin file exists, if it doesn't it needs to fail below.
		if ( file_exists( $abs_plugin ) ) {

			// Don't activate if already active.
			if ( is_plugin_active( $plugin ) ) {
				return;
			}

			// Don't activate if already network-active.
			if ( $network && is_plugin_active_for_network( $plugin ) ) {
				return;
			}
		}

		// Activate the plugin.
		$result = activate_plugin( $plugin, null, $network_wide );

		// If we activated correctly, than return results of that.
		if ( ! is_wp_error( $result ) ) {
			return;
		}

		/**
		 * Filter if a plugin is not found (that's required).
		 *
		 * For instance to disable all logging you could:
		 *
		 *     add_filter( 'wds_required_plugin_log_if_not_found', '__return_false' );
		 *
		 * Or, you could do it on a case-by-case basis with the $plugin being sent.
		 *
		 * @author  Justin Sternberg
		 * @since   Unknown
		 *
		 * @param boolean $log_not_found Whether the plugin is indeed found or not,
		 *                               default to true in the normal case. Set to false
		 *                               if you would like to override that and not log it,
		 *                               for instance, if it's intentional.
		 */
		$log_not_found = apply_filters( 'wds_required_plugin_log_if_not_found', true, $plugin, $result, $network );

		if ( ! $log_not_found ) {
			return;
		}

		// translators: %1 and %2 are explained below. Set default log text.
		$default_log_text = __( 'Required Plugin auto-activation failed for: %1$s, with message: %2$s', 'wds-required-plugins' );

		// Filter the logging message format/text.
		$log_msg_format = apply_filters( 'wds_required_plugins_error_log_text', $default_log_text, $plugin, $result, $network );

		// Get our error message.
		$error_message = method_exists( $result, 'get_error_message' ) ? $result->get_error_message() : '';

		// The message.
		$s_message = sprintf( esc_attr( $log_msg_format ), esc_attr( $plugin ), esc_attr( $error_message ) );

		/**
		 * Filter whether we should stop if a plugin is not found.
		 *
		 * @since  1.1.0
		 * @author Aubrey Portwood <aubrey@webdevstudios.com>
		 *
		 * @param boolean $stop_not_found Set to false to not halt execution if a plugin is not found.
		 */
		$stop_not_found = apply_filters( 'wds_required_plugin_stop_if_not_found', false, $plugin, $result, $network );

		if ( $stop_not_found ) {
			throw new Exception( $s_message );
		} else {

			// @codingStandardsIgnoreLine: Throw the right kind of error.
			trigger_error( $s_message );
		}
	}

	/**
	 * The required plugin label text.
	 *
	 * @since  0.1.0
	 * @author Unknown
	 */
	public function required_text_markup() {
		$default = sprintf( $this->required_text_code, __( 'Required Plugin', 'wds-required-plugins' ) );

		/**
		 * Set the value for what shows when a plugin is required.
		 *
		 * E.g. by default it's Required, but you could change it to
		 * "Cannot Deactivate" if you wanted to.
		 *
		 * @author Justin Sternberg
		 * @since  Unknown
		 *
		 * @param string $default The default value that you can change.
		 */
		$filtered = apply_filters( 'wds_required_plugins_text', $default );

		// The property on this object we'll set for use later.
		if ( is_string( $filtered ) ) {
			$this->required_text = $filtered;
		} else {
			$this->required_text = $default;
		}
	}

	/**
	 * Remove the deactivation link for all custom/required plugins
	 *
	 * @since 0.1.0
	 *
	 * @param array  $actions  Array of actions avaible.
	 * @param string $plugin   Slug of plugin.
	 *
	 * @author Justin Sternberg
	 * @author Brad Parbs
	 * @author Aubrey Portwood Added documentation for filters.
	 *
	 * @return array
	 */
	public function filter_plugin_links( $actions = array(), $plugin ) {

		// Get our required plugins for network + normal.
		$required_plugins = array_unique( array_merge( $this->get_required_plugins(), $this->get_network_required_plugins() ) );

		// Replace these action keys with what we have set for required text.
		$action_keys = array(
			'deactivate',
			'network_active',
		);

		foreach ( $action_keys as $key ) {

			// Remove deactivate link for required plugins.
			if ( array_key_exists( $key, $actions ) && in_array( $plugin, $required_plugins, true ) ) {

				/**
				 * Should we remove the deactivated text for this plugin?
				 *
				 * @author  Brad Parbs
				 * @since   Unknown
				 *
				 * @param boolean $remove Should we remove it? Default to true.
				 * @param string  $plugin What plugin we're talking about.
				 */
				$wds_required_plugin_network_activate = apply_filters( 'wds_required_plugin_network_activate', true, $plugin );

				// Filter if you don't want the required plugin to be network-required by default.
				if ( $wds_required_plugin_network_activate ) {
					$actions[ $key ] = $this->required_text;
				}
			}
		}

		return $actions;
	}

	/**
	 * Remove required plugins from the plugins list, if enabled.
	 *
	 * Must be enabled using the wds_required_plugin_remove_from_list filter.
	 * When enabled, all the plugins that end up being WDS Required
	 * also do not show in the plugins list.
	 *
	 * @since   0.1.5
	 * @author  Brad Parbs
	 * @author  Aubrey Portwood Made it so mu-plugins are also unseen.
	 *
	 * @param   array $plugins Array of plugins.
	 * @return  array          Array of plugins.
	 */
	public function maybe_remove_plugins_from_list( $plugins ) {

		/**
		 * Set to true to skip removing plugins from the list.
		 *
		 * Default to false (disabled).
		 *
		 * E.g.:
		 *
		 *     add_filter( 'wds_required_plugin_remove_from_list', '__return_true' );
		 *
		 * @author  Brad Parbs
		 * @since   Unknown
		 *
		 * @param array $enabled Whether or not removing all plugins from the list is enabled.
		 */
		$enabled = apply_filters( 'wds_required_plugin_remove_from_list', false );

		// Allow for removing all plugins from the plugins list.
		if ( false === $enabled ) {

			// Do not remove any plugins.
			return $plugins;
		}

		// Loop through each of our required plugins.
		foreach ( array_merge( $this->get_required_plugins(), $this->get_network_required_plugins() ) as $required_plugin ) {

			// Remove from the all plugins list.
			unset( $plugins[ $required_plugin ] );
		}

		// Send it back.
		return $plugins;
	}

	/**
	 * Get the plugins that are required for the project. Plugins will be registered by the wds_required_plugins filter
	 *
	 * @author Justin Sternberg
	 * @author Aubrey Portwood  Added filter documentation.
	 * @since  0.1.0
	 *
	 * @return array
	 */
	public function get_required_plugins() {

		/**
		 * Set single site required plugins.
		 *
		 * Example:
		 *
		 *     function wds_required_plugins_add( $required ) {
		 *         $required = array_merge( $required, array(
		 *             'akismet/akismet.php',
		 *             'wordpress-importer/wordpress-importer.php',
		 *         ) );
		 *
		 *         return $required;
		 *     }
		 *     add_filter( 'wds_network_required_plugins', 'wds_required_plugins_add' );
		 *
		 * @author Brad Parbs
		 * @author Aubrey Portwood
		 *
		 * @since  Unknown
		 *
		 * @var array
		 */
		$required_plugins = apply_filters( 'wds_required_plugins', array() );
		if ( ! is_array( $required_plugins ) ) {

			// The person who filtered this broke it.
			return array();
		}

		$required_plugins = array_merge( $required_plugins, $this->get_header_required_plugins() );

		return $required_plugins;
	}

	/**
	 * Get the network plugins that are required for the project. Plugins will be registered by the wds_network_required_plugins filter
	 *
	 * @since  0.1.3
	 * @author Patrick Garman
	 *
	 * @since  1.0.0  Cleanup and rewrite.
	 * @author Aubrey Portwood <aubrey@webdevstudios.com>
	 *
	 * @return array
	 */
	public function get_network_required_plugins() {

		/**
		 * Set multisite site required plugins.
		 *
		 * Example:
		 *
		 *     function wds_required_plugins_add( $required ) {
		 *         $required = array_merge( $required, array(
		 *             'akismet/akismet.php',
		 *             'wordpress-importer/wordpress-importer.php',
		 *         ) );
		 *
		 *         return $required;
		 *     }
		 *     add_filter( 'wds_network_required_plugins', 'wds_required_plugins_add' );
		 *
		 * @author Brad Parbs
		 * @author Aubrey Portwood
		 *
		 * @since  Unknown
		 *
		 * @var array
		 */
		$required_plugins = apply_filters( 'wds_network_required_plugins', array() );
		if ( ! is_array( $required_plugins ) ) {

			// The person who filtered this broke it.
			return array();
		}

		return $required_plugins;
	}

	/**
	 * Load this library's text domain.
	 *
	 * @author Justin Sternberg
	 * @author Brad Parbs
	 * @since  0.1.1
	 *
	 * @return void Early bails when it's un-necessary.
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

	/**
	 * Adds a header field for required plugins when WordPress reads plugin data.
	 *
	 * @since 1.2.0
	 * @author Zach Owen
	 *
	 * @param array $extra_headers Extra headers filtered in WP core.
	 * @return array
	 */
	public function add_required_plugin_header( $extra_headers ) {
		$required_header = $this->get_required_header();

		if ( in_array( $required_header, $extra_headers, true ) ) {
			return $extra_headers;
		}

		$extra_headers[] = $required_header;
		return $extra_headers;
	}

	/**
	 * Return a list of plugins with the required header set.
	 *
	 * @since 1.2.0
	 * @author Zach Owen
	 *
	 * @return array
	 */
	public function get_header_required_plugins() {
		$all_plugins = apply_filters( 'all_plugins', get_plugins() );

		if ( empty( $all_plugins ) ) {
			return [];
		}

		$required_header = $this->get_required_header();
		$plugins         = [];

		/**
		 * Filter the value for the header that would indicate the plugin as required.
		 *
		 * @author Aubrey Portwood <aubrey@webdevstudios.com>
		 * @since  1.2.0
		 *
		 * @var array
		 */
		$values = apply_filters( 'wds_required_plugins_required_header_values', [
			'true',
			'yes',
			'1',
			'on',
			'required',
			'require',
		] );

		foreach ( $all_plugins as $file => $headers ) {
			if ( ! in_array( $headers[ $required_header ], $values, true ) ) {
				continue;
			}

			$plugins[] = $file;
		}

		return $plugins;
	}

	/**
	 * Get the key to use for the required plugin header identifier.
	 *
	 * @author Zach Owen
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function get_required_header() {
		$header_text = 'Required';

		/**
		 * Filter the text used as the identifier for the plugin being
		 * required.
		 *
		 * @author Zach Owen
		 * @since 1.2.0
		 *
		 * @param string $header The string to use as the identifier.
		 */
		$header = apply_filters( 'wds_required_plugin_header', $header_text );

		if ( ! is_string( $header ) || empty( $header ) ) {
			return $header_text;
		}

		return $header;
	}
}

// Init.
Plugin::init();
