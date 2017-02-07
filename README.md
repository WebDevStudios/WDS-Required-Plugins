WDS Required Plugins
=========

A library intended for mu-plugins and used in [wd_s](https://github.com/WebDevStudios/wd_s) that allows a theme or plugin to filter the list of required plugins so that:
* The deactivate links are removed.
* Plugins are automatically activated (if they are in the plugins directory)
* More to come.

To use, place this library in your mu-plugins/ directory (if you don't have one, create one in wp-content/), then use the example below:

#### Example Usage:
```php
<?php

require WPMU_PLUGIN_DIR . '/WDS-Required-Plugins/wds-required-plugins.php';

/**
 * Add required plugins to WDS_Required_Plugins
 *
 * @param  array $required Array of required plugins in `plugin_dir/plugin_file.php` form
 *
 * @return array           Modified array of required plugins
 */
function wds_required_plugins_add( $required ) {

	$required = array_merge( $required, array(
		'jetpack/jetpack.php',
		'sample-plugin/sample-plugin.php',
	) );

	return $required;
}
add_filter( 'wds_required_plugins', 'wds_required_plugins_add' );
// Or network-activate/require them:
// add_filter( 'wds_network_required_plugins', 'wds_required_plugins_add' );
```

#### Modification:
To change the label from 'WDS Required Plugin', use the following filter/code.

```php

/**
 * Modify the required-plugin label
 *
 * @param  string  $label Label markup
 *
 * @return string         (modified) label markup
 */
function change_wds_required_plugins_text( $label ) {

	$label_text = __( 'Required Plugin for ACME', 'acme-prefix' );
	$label = sprintf( '<span style="color: #888">%s</span>', $label_text );

	return $label;
}
add_filter( 'wds_required_plugins_text', 'change_wds_required_plugins_text' );
```

#### Hide from the Plugin list
To hide your required plugins from the plugins list, use the following filter/code.

```php
add_filter( 'wds_required_plugins_remove_from_list', '__return_true' );
```

#### Changelog
* 0.1.5
	* Add ability to remove plugins from the plugin list, if desired.
	* Comments / docblocks clean up.
* 0.1.4
	* Will now log if/when a required plugin is not found.
	* New filters:
		* `'wds_required_plugin_auto_activate'` - By default required plugins are auto-activated. This filter can disable that.
		* `'wds_required_plugin_log_if_not_found'` - By default, missing required plugins will trigger an error in your log. This filter can disable that.
		* `'wds_required_plugins_error_log_text'` - Filters the text format for the log entry.
* 0.1.3
	* Network activation filter
* 0.1.2
	* i10n
* 0.1.1
	* Automatically activate required plugins (if they are available).
* 0.1.0
	* Hello World.
