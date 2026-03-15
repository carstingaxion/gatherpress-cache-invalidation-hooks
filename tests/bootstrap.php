<?php
/**
 * PHPUnit bootstrap file for the GatherPress Cache Invalidation Hooks.
 *
 * Supports both wp-env and local WordPress test environments.
 * Requires GatherPress to be installed and activated in the test environment.
 *
 * Usage with wp-env:
 *   wp-env run tests-cli --env-cwd='wp-content/plugins/gatherpress-cache-invalidation-guide' \
 *     bash -c 'WP_TESTS_DIR=/wordpress-phpunit composer test'
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 */

// Composer autoloader for test dependencies.
$autoloader = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $autoloader ) ) {
	require_once $autoloader;
}

// Determine the WordPress test suite location.
// Priority: WP_TESTS_DIR env var > wp-env default > local fallback.
$wp_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wp_tests_dir ) {
	// wp-env default location for the test suite.
	$wp_tests_dir = '/wordpress-phpunit';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	// Try the system tmp directory as a fallback (common for local installs).
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $wp_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at: {$wp_tests_dir}" . PHP_EOL;
	echo PHP_EOL;
	echo 'Set the WP_TESTS_DIR environment variable to point to your WordPress test suite.' . PHP_EOL;
	echo 'When using wp-env, run:' . PHP_EOL;
	echo '  npx wp-env run tests-cli --env-cwd="wp-content/plugins/gatherpress-cache-invalidation-guide" bash -c "WP_TESTS_DIR=/wordpress-phpunit vendor/bin/phpunit"' . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $wp_tests_dir . '/includes/functions.php';

/**
 * Manually load GatherPress and this plugin before tests run.
 *
 * GatherPress must load first because our plugin depends on it
 * (uses Core\Traits\Singleton, Core\Event, etc.).
 *
 * This function is hooked into `muplugins_loaded` so it runs before WordPress
 * finishes loading. This ensures our plugin code is available for all tests.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		// Load GatherPress first - our plugin depends on it.
		$gatherpress_path = WP_PLUGIN_DIR . '/gatherpress/gatherpress.php';
		if ( file_exists( $gatherpress_path ) ) {
			require $gatherpress_path;
		} else {
			echo 'GatherPress plugin not found at: ' . $gatherpress_path . PHP_EOL;
			echo 'Ensure GatherPress is installed in the test environment.' . PHP_EOL;
			echo 'The .wp-env.json should include GatherPress in the plugins list.' . PHP_EOL;
			exit( 1 );
		}

		// Load our plugin.
		require dirname( __DIR__ ) . '/plugin.php';
	}
);

// Start up the WP testing environment.
require $wp_tests_dir . '/includes/bootstrap.php';
