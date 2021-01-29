<?php

namespace \WebDevStudios\Required_Plugins\WP\CLI;

class CLI {
	public function run() : void {
		$this->register_hooks();
	}

	private function register_hooks() : void {
		add_action( 'cli_init', [ $this, 'add_command' ] );
	}

	public function add_command() : void {
		add_command( 'require', [ $this, 'run_command' ], [
			'shortdesc' => __( "Require a plugin.", 'wds-required-plugins' ),
		] );
	}

	public function run_command( array $args, array $assoc_args ) : void {
		WP_CLI::log( 'out' );
	}
}

new CLI()->run();
