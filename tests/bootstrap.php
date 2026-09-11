<?php
/**
 * Bootstrap the do_action WordPress integration tests.
 *
 * @package do_action
 */

declare( strict_types = 1 );

$do_action_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $do_action_tests_dir ) {
	$do_action_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_TESTS_CONFIG_FILE_PATH' ) );
}

if ( getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) );
}

require_once $do_action_tests_dir . '/includes/functions.php';

tests_add_filter(
	'init',
	/**
	 * Load the plugin before its init callbacks, after translations are available.
	 *
	 * @return void
	 */
	static function (): void {
		require dirname( __DIR__ ) . '/wp-content/plugins/do-action/do-action.php';
	},
	0
);

require $do_action_tests_dir . '/includes/bootstrap.php';
