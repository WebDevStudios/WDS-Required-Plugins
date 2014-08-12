WDS Required Plugins
=========

A library intended for mu-plugins and used in [wd_s](https://github.com/WebDevStudios/wd_s) that allows a theme or plugin to filter the list of required plugins so that the deactivate links are removed. More to come.

#### Example Usage:
```php
<?php
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
```
