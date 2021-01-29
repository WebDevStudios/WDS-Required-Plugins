<?php

namespace WebDevStudios\Required_Plugins\WP\CLI;

class CLI {
	public function run() : void {
		$this->register_hooks();
	}

	private function register_hooks() : void {
		add_action( 'cli_init', [ $this, 'add_command' ] );
	}

	public function add_command() : void {
		\WP_CLI::add_command( 'require', [ $this, 'run_command' ], [
			'shortdesc' => __( "Require a plugin.", 'wds-required-plugins' ),
		] );
	}

	public function run_command( array $args, array $assoc_args ) : void {
		$this->require( $this->resolve_plugin_file( $args[0] ?? '' ) );
	}

	private function require( string $file ) : void {
		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( sprintf( __( "Could not location plugin: %s", 'wds-required-plugins' ), "{$file}" ) );
		}
	}

	private function resolve_plugin_file( string $file ) : string {
		if ( file_exists( $file ) ) {
			return $file; // What they gave us is there.
		}

		// Try and use from working directory, maybe it's relative.
		return getcwd() . "/{$file}";
	}
}

function init() {
	$cli = new CLI();
	$cli->run();
}

init();
