# WDS Required Plugins

A library you can use to make any plugins required and auto-activate.

* Nobody can de-activate the plugin from the WordPress Admin
* They are auto-activated when required

To use, place this library in your `mu-plugins/` directory (if you don't have one, create one in `wp-content/`), then use the example below:

<a href="https://webdevstudios.com/contact/"><img src="https://webdevstudios.com/wp-content/uploads/2018/04/wds-github-banner.png" alt="WebDevStudios. WordPress for big brands."></a>

## Installation & Update

### With Composer

Add the following to your `composer.json`

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "git@github.com:WebDevStudios/WDS-Required-Plugins.git"
        }
    ],
    "require": {
        "webdevstudios/wds-required-plugins": "dev-master"
    },
    "extra": {
        "installer-paths": {
            "mu-plugins/{$name}/": ["type:wordpress-muplugin"]
        }
    }
}
```

This will install the `mu-plugin`, e.g. `mu-plugins/wds-required-plugins` in `wp-content` based projects. You will have to require it in e.g. `mu-plugins/wds-required-plugins-list.php`:

```php
<?php

require WPMU_PLUGIN_DIR . '/wds-required-plugins/wds-required-plugins.php';

function wds_required_plugins_add( $required ) {
    return array_merge( $required, array(
        'my-plugin/my-plugin.php`,
    ) );
}
add_filter( 'wds_network_required_plugins', 'wds_required_plugins_add' );

```

### Without Composer

You can easily run the below command from the `wp-content/mu-plugins` directory:

```sh
curl --remote-name https://raw.githubusercontent.com/WebDevStudios/WDS-Required-Plugins/master/wds-required-plugins.php
```

This will download and install the plugin automatically (and will update your file).

#### Clone the Repo

You can also clone the repo into your `wp-content/mu-plugins` directory, but you will have
to load the library via before any of the examples below.

```php
require_once WPMU_PLUGIN_DIR . '/WDS-Required-Plugins/wds-required-plugins.php';
```

## Example Usage

```php
<?php

/**
 * Add required plugins to WDS_Required_Plugins.
 *
 * @param  array $required Array of required plugins in `plugin_dir/plugin_file.php` form.
 *
 * @return array           Modified array of required plugins.
 */
function wds_required_plugins_add( $required ) {

	$required = array_merge( $required, array(
		'jetpack/jetpack.php',
		'sample-plugin/sample-plugin.php',
	) );

	return $required;
}
add_filter( 'wds_required_plugins', 'wds_required_plugins_add' );
```

Use the following filter **instead** to network activate plugins:

```php
add_filter( 'wds_network_required_plugins', 'wds_required_plugins_add' );
```

### Change the Text:

To change the label from **Required Plugin** to something else, use the following filter/code:

```php

/**
 * Modify the required-plugin label.
 *
 * @param  string  $label Label markup.
 *
 * @return string         (modified) label markup.
 */
function change_wds_required_plugins_text( $label ) {

	$label_text = __( 'Required Plugin for ACME', 'acme-prefix' );
	$label = sprintf( '<span style="color: #888">%s</span>', $label_text );

	return $label;
}
add_filter( 'wds_required_plugins_text', 'change_wds_required_plugins_text' );
```

### Hide Required Plugins (off by default)

To hide your required plugins from the plugins list, use the following filter/code:

```php
add_filter( 'wds_required_plugins_remove_from_list', '__return_true' );
```

This will make any plugin that is required simply not show in the plugins list.

____________________

# Changelog

## 1.1.0

- You can have the project throw an `Exception` if there is an activation problem
    - Use filter `wds_required_plugin_stop_if_not_found` set to `true` to throw
    - *Note, this will be default behavior in a future release*
    - Just add `add_filter( 'wds_required_plugin_stop_if_not_found', '__return_true' );`
- Installable via `composer`

## 1.0.0

- General code cleanup to get up to WDSCS
- Will not break with using with WP Migrate DB PRO
- Added incompatibility checks so you can disable it when certain actions are executed
- Will not break when installing Multisite
- *Required Plugin* is now shown on required plugins across multisite
- Better code: more sanity checking and validations to avoid breakage
- Better installation and update process, see `README.md`

## 0.1.5

- Add ability to remove plugins from the plugin list, if desired.
- Comments / docblocks clean up.

## 0.1.4

- Will now log if/when a required plugin is not found.
- New filters:
    - `'wds_required_plugin_auto_activate'` - By default required plugins are auto-activated. This filter can disable that.
    - `'wds_required_plugin_log_if_not_found'` - By default, missing required plugins will trigger an error in your log. This filter can disable that.
    - `'wds_required_plugins_error_log_text'` - Filters the text format for the log entry.

