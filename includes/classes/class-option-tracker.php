<?php
/**
 * Upcoming Events Option Tracker System (Optional)
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 */

namespace GatherPress_Cache_Invalidation_Hooks;

use GatherPress\Core;
use WP_Post;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! class_exists( 'Option_Tracker' ) ) {
	/**
	 * Upcoming Events Option Tracker (Optional)
	 *
	 * An optional redundant tracking system can be enabled via filter to prevent missed events:
	 * 1. Each post type that supports `gatherpress-event-date` gets its own wp_option tracking list.
	 * 2. A daily cron job checks every list for posts whose end date has passed.
	 * 3. If a scheduled cron job failed, the daily check catches it and triggers cleanup.
	 *
	 * One option key is maintained per supporting post type:
	 *   `upcoming_{$post_type}s`
	 *
	 * This avoids mixing IDs from different post types in a single option and ensures that
	 * validation always uses the correct GatherPress event object for the right type.
	 *
	 * Why Optional:
	 * The upcoming events option tracker requires additional database writes on every event
	 * status change. Most sites have reliable cron systems and don't need this redundancy.
	 * It's disabled by default to minimize unnecessary database operations, but can be enabled
	 * for high-value deployments where missing an event cleanup would be critical.
	 *
	 * To enable upcoming events option tracker:
	 * add_filter( 'gatherpress_upcoming_events_option_tracker_enabled', '__return_true' );
	 *
	 * @package GatherPress\Cache_Invalidation_Hooks
	 * @since 0.1.0
	 */
	class Option_Tracker {

		use Core\Traits\Singleton;

		/**
		 * The WordPress cron hook for the daily check.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const CRON_HOOK = 'gatherpress_validate_events_ended';

		/**
		 * Prefix for the per-post-type wp_option tracking keys.
		 *
		 * The full key for a given post type is built as:
		 *   self::OPTION_KEY_PREFIX . $post_type . 's'
		 *
		 * e.g. 'upcoming_gatherpress_events'
		 *      'upcoming_gatherpress_productions'
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const OPTION_KEY_PREFIX = 'upcoming_';

		/**
		 * Constructor for the Setup class.
		 *
		 * Initializes and sets up various components of the plugin.
		 *
		 * @since 0.1.0
		 */
		protected function __construct() {
			$this->setup_hooks();
		}

		/**
		 * Set up hooks for various purposes.
		 *
		 * Registers all necessary hooks:
		 * - Monitors post status changes to manage redundant event tracking.
		 * - Cleans up tracking before post deletion.
		 * - Performs daily checks for ended events that may have been missed.
		 * - Schedules the daily cron job if enabled.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		protected function setup_hooks(): void {
			// Always register the action hooks. Each callback checks per-post-type
			// enablement at runtime so newly enabled types are picked up without
			// needing to flush any cached gate result.
			add_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', array( $this, 'add_to_tracking' ), 10, 2 );
			add_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this, 'remove_from_tracking' ), 10, 2 );
			add_action( Cron_Scheduler::ACTION_HOOK, array( $this, 'remove_from_tracking' ), 10, 2 );
			add_action( self::CRON_HOOK, array( $this, 'validate_events_ended' ) );

			// Schedule the daily cron only when at least one post type is enabled.
			if ( $this->any_post_type_enabled() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'daily', self::CRON_HOOK );
			}
		}

		/**
		 * Returns true when the tracker is enabled for the given post type.
		 *
		 * Resolution order (first truthy value wins):
		 * 1. Deprecated filter `gatherpress_upcoming_events_option_tracker_enabled`
		 *    — handled via `apply_filters_deprecated()`, which fires a notice and
		 *      still runs any hooked callbacks for backwards compatibility.
		 * 2. General filter `gatherpress_upcoming_tracker_enabled` — enables all
		 *    supporting post types at once.
		 * 3. Per-type filter `{$post_type}_upcoming_tracker_enabled` — enables a
		 *    single post type independently.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_type The post type slug to check.
		 * @return bool True when the tracker should run for this post type.
		 */
		public function is_post_type_enabled( string $post_type ): bool {
			// 1. Deprecated filter — apply_filters_deprecated() fires the notice
			// and runs any hooked callbacks so backwards compatibility is preserved.
			if ( apply_filters_deprecated(
				'gatherpress_upcoming_events_option_tracker_enabled',
				array( false ),
				'0.2.0',
				sprintf(
					/* translators: 1: new general filter name, 2: per-type filter pattern */
					__(
						'Use "%1$s" to enable all post types, or "%2$s" for a specific post type.',
						'gatherpress-cache-invalidation-hooks'
					),
					'gatherpress_upcoming_tracker_enabled',
					'{post_type}_upcoming_tracker_enabled'
				)
			) ) {
				return true;
			}

			/**
			 * Filter whether to enable the tracker for all supporting post types at once.
			 *
			 * When true, every post type that declares `gatherpress-event-date` support
			 * gets its own tracking option and is included in the daily cron check.
			 *
			 * @example
			 * ```php
			 * add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
			 * ```
			 *
			 * @since 0.2.0
			 *
			 * @param bool   $enabled   Whether the tracker is globally enabled. Default false.
			 * @param string $post_type The post type currently being evaluated.
			 */
			if ( apply_filters( 'gatherpress_upcoming_tracker_enabled', false, $post_type ) ) {
				return true;
			}

			/**
			 * Filter whether to enable the tracker for a specific post type.
			 *
			 * The dynamic portion of the hook name, `$post_type`, refers to the
			 * post type slug, e.g. `gatherpress_event` or `gatherpress_production`.
			 *
			 * Possible hook names include:
			 *  - `gatherpress_event_upcoming_tracker_enabled`
			 *  - `gatherpress_production_upcoming_tracker_enabled`
			 *
			 * @example
			 * ```php
			 * add_filter( 'gatherpress_event_upcoming_tracker_enabled', '__return_true' );
			 * ```
			 *
			 * @since 0.2.0
			 *
			 * @param bool $enabled Whether the tracker is enabled for this post type. Default false.
			 */
			return (bool) apply_filters( "{$post_type}_upcoming_tracker_enabled", false );
		}

		/**
		 * Returns true when at least one supporting post type has the tracker enabled.
		 *
		 * Used by setup_hooks() to decide whether to schedule the daily cron.
		 *
		 * @since 0.2.0
		 *
		 * @return bool
		 */
		public function any_post_type_enabled(): bool {
			foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $post_type ) {
				if ( $this->is_post_type_enabled( $post_type ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Returns the wp_option key for tracking a specific post type.
		 *
		 * Each post type that declares `gatherpress-event-date` support gets its
		 * own option so IDs from different types are never mixed.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_type The post type slug.
		 * @return string The option key, e.g. 'upcoming_gatherpress_events'.
		 */
		public function option_key_for( string $post_type ): string {
			return self::OPTION_KEY_PREFIX . $post_type . 's';
		}

		/**
		 * Daily cron job to check for events that ended but weren't processed.
		 *
		 * Iterates every post type that declares `gatherpress-event-date` support,
		 * reads its dedicated tracking option, and validates each stored ID.
		 *
		 * If a scheduled cron job fails (server issues, high load, etc.), this
		 * daily check ensures we eventually catch and process ended events.
		 *
		 * Logic flow per post type:
		 * 1. Read the per-type option (list of upcoming post IDs).
		 * 2. For each ID: load the post, confirm it still has the right post type.
		 * 3. Instantiate Core\Event and check has_event_past().
		 * 4. If ended, fire ACTION_HOOK (which also removes from tracking).
		 * 5. If the post no longer exists or has wrong type, remove from tracking.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function validate_events_ended(): void {
			$supporting_types = get_post_types_by_support( 'gatherpress-event-date' );

			foreach ( $supporting_types as $post_type ) {
				$this->validate_events_ended_for_type( $post_type );
			}
		}

		/**
		 * Validates tracked event IDs for a single post type.
		 *
		 * Separated from validate_events_ended() so each post type is processed
		 * independently — a bad ID in one type's list cannot abort processing of
		 * another type's list.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_type The post type slug to validate.
		 * @return void
		 */
		private function validate_events_ended_for_type( string $post_type ): void {
			if ( ! $this->is_post_type_enabled( $post_type ) ) {
				return;
			}

			$tracked_ids = $this->get_tracked_ids( $post_type );

			if ( empty( $tracked_ids ) ) {
				return;
			}

			foreach ( $tracked_ids as $post_id ) {
				$post = get_post( $post_id );

				// Clean up stale entries: post gone or post type changed.
				if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
					$this->remove_id_from_option( $post_id, $post_type );
					continue;
				}

				$event = new Core\Event( $post_id );

				// @phpstan-ignore-next-line
				if ( ! method_exists( $event, 'has_event_past' ) ) {
					continue;
				}

				if ( $event->has_event_past() ) {
					/**
					 * Trigger the main event end action hook.
					 *
					 * Central hook for event end processing. All cleanup operations
					 * (cache invalidation, tracking removal, etc.) are hooked to this
					 * action at various priorities.
					 *
					 * @since 0.1.0
					 * @param int        $post_id The ID of the event that ended.
					 * @param Core\Event $event   The GatherPress event object.
					 */
					do_action( Cron_Scheduler::ACTION_HOOK, $post_id, $event );
				}
			}
		}

		/**
		 * Add a post ID to the tracking list for its post type.
		 *
		 * Determines the post type from the WP_Post object passed as the second
		 * argument (provided by gatherpress_cache_invalidation_hooks_new_upcoming)
		 * and writes the ID into the dedicated per-type option.
		 *
		 * Only operates when the upcoming events option tracker is enabled.
		 *
		 * @since 0.1.0
		 *
		 * @param int     $post_id The event post ID to add to tracking.
		 * @param WP_Post $post    The post object (used to resolve the post type).
		 *
		 * @return void
		 */
		public function add_to_tracking( int $post_id, WP_Post $post ): void {
			if ( ! $this->is_post_type_enabled( $post->post_type ) ) {
				return;
			}

			$tracked_ids   = $this->get_tracked_ids( $post->post_type );
			$tracked_ids[] = $post_id;
			$tracked_ids   = array_values( array_unique( $tracked_ids ) );

			update_option( $this->option_key_for( $post->post_type ), $tracked_ids );
		}

		/**
		 * Remove a post ID from the tracking list for its post type.
		 *
		 * Called when a post is unpublished, deleted, or successfully processed.
		 * The second argument accepts either a WP_Post or null so the method can
		 * be called from ACTION_HOOK which passes a Core\Event as second arg —
		 * in that case the post type is resolved from the event object itself.
		 *
		 * Only operates when the upcoming events option tracker is enabled.
		 *
		 * @since 0.1.0
		 *
		 * @param int                    $post_id The event post ID to remove from tracking.
		 * @param WP_Post|Core\Event|int $context WP_Post, Core\Event, or a bare post ID.
		 *                                        Used only to resolve the post type.
		 *
		 * @return void
		 */
		public function remove_from_tracking( int $post_id, WP_Post|Core\Event|int $context = 0 ): void {
			$post_type = $this->resolve_post_type( $post_id, $context );

			if ( '' === $post_type ) {
				// Fallback: scrub the ID from all enabled supporting types.
				foreach ( get_post_types_by_support( 'gatherpress-event-date' ) as $type ) {
					if ( $this->is_post_type_enabled( $type ) ) {
						$this->remove_id_from_option( $post_id, $type );
					}
				}
				return;
			}

			if ( ! $this->is_post_type_enabled( $post_type ) ) {
				return;
			}

			$this->remove_id_from_option( $post_id, $post_type );
		}

		/**
		 * Reads and normalises the tracking list for a given post type.
		 *
		 * @since 0.1.0
		 *
		 * @param string $post_type The post type slug.
		 * @return array<int> Array of tracked post IDs (may be empty).
		 */
		private function get_tracked_ids( string $post_type ): array {
			$raw = get_option( $this->option_key_for( $post_type ), array() );

			if ( ! is_array( $raw ) ) {
				return array();
			}

			return array_values(
				array_filter(
					array_map(
						static function ( mixed $v ): int {
							return is_scalar( $v ) ? (int) $v : 0;
						},
						$raw
					),
					static fn( int $id ) => $id > 0
				)
			);
		}

		/**
		 * Removes a single ID from the option for the given post type.
		 *
		 * @since 0.1.0
		 *
		 * @param int    $post_id   The post ID to remove.
		 * @param string $post_type The post type whose option to update.
		 * @return void
		 */
		private function remove_id_from_option( int $post_id, string $post_type ): void {
			$tracked_ids = $this->get_tracked_ids( $post_type );
			$tracked_ids = array_values( array_diff( $tracked_ids, array( $post_id ) ) );

			update_option( $this->option_key_for( $post_type ), $tracked_ids );
		}

		/**
		 * Resolves a post type string from a mixed context argument.
		 *
		 * @since 0.1.0
		 *
		 * @param int                    $post_id The post ID (fallback lookup).
		 * @param WP_Post|Core\Event|int $context Context passed by the caller.
		 * @return string Post type slug, or '' when it cannot be determined.
		 */
		private function resolve_post_type( int $post_id, WP_Post|Core\Event|int $context ): string {
			if ( $context instanceof WP_Post ) {
				return $context->post_type;
			}

			if ( $context instanceof Core\Event && isset( $context->event ) ) {
				return $context->event->post_type;
			}

			// Fall back to a fresh get_post() lookup (context is int or unresolvable Event).
			$post = get_post( $post_id );
			return $post instanceof WP_Post ? $post->post_type : '';
		}
	}
}
