<?php
/**
 * Plugin Name:  GatherPress Cache Invalidation Hooks
 * Plugin URI:   
 * Description:  Cache Invalidation system based on event end dates, similar to WordPress scheduled posts, but for GatherPress.
 * Author:       carstenbach & WordPress Telex
 * Author URI:   
 * Version:      0.1.0
 * Requires PHP: 7.4
 * Requires Plugins:  gatherpress
 * Text Domain:  gatherpress-cache-invalidation-hooks
 * Domain Path:  /languages
 * License:      GNU General Public License v2.0 or later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

// Constants.
define( 'GATHERPRESS_CACHE_INVALIDATION_HOOKS_VERSION', current( get_file_data( __FILE__, array( 'Version' ), 'plugin' ) ) );
define( 'GATHERPRESS_CACHE_INVALIDATION_HOOKS_CORE_PATH', __DIR__ );

/**
 * Adds the GatherPress\Cache_Invalidation_Hooks namespace to the autoloader.
 *
 * This function hooks into the 'gatherpress_autoloader' filter and adds the
 * GatherPress\Cache_Invalidation_Hooks namespace to the list of namespaces with its core path.
 *
 * @param array<string, string> $namespaces An associative array of namespaces and their paths.
 * @return array<string, string> Modified array of namespaces and their paths.
 */
function gatherpress_cache_invalidation_hooks_autoloader( array $namespaces ): array {
	$namespaces['GatherPress_Cache_Invalidation_Hooks'] = GATHERPRESS_CACHE_INVALIDATION_HOOKS_CORE_PATH;

	return $namespaces;
}
add_filter( 'gatherpress_autoloader', 'gatherpress_cache_invalidation_hooks_autoloader' );

/**
 * Initializes the setup.
 *
 * This function hooks into the 'plugins_loaded' action to ensure that
 * the instances are created once all plugins are loaded,
 * only if the GatherPress plugin is active.
 *
 * @return void
 */
function gatherpress_cache_invalidation_hooks_setup(): void {
	if ( defined( 'GATHERPRESS_VERSION' ) ) {
		GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::get_instance();
		GatherPress_Cache_Invalidation_Hooks\Option_Tracker::get_instance();
	}
}
add_action( 'plugins_loaded', 'gatherpress_cache_invalidation_hooks_setup' );
