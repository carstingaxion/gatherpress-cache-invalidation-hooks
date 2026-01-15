<?php
/**
 * Upcoming Events Option Tracker System (Optional)
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 */

namespace GatherPress_Cache_Invalidation_Hooks;

use GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! class_exists( 'Option_Tracker' ) ) {
	/**
	 * Upcoming Events Option Tracker (Optional)
	 * 
	 * An optional redundant tracking system can be enabled via filter to prevent missed events:
	 * 1. All upcoming events are stored in a wp_option array
	 * 2. A daily cron job checks this list for any events that have ended
	 * 3. If an event's cron job failed, the daily check catches it and triggers cleanup
	 *
	 * Why Optional:
	 * The upcoming events option tracker requires additional database writes on every event status change.
	 * Most sites have reliable cron systems and don't need this redundancy. It's disabled
	 * by default to minimize unnecessary database operations, but can be enabled for
	 * high-value deployments where missing an event cleanup would be critical.
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
		 * The wp_option key for tracking upcoming events.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const OPTION_KEY = 'gatherpress_upcoming_events';

		/**
		 * The GatherPress event custom post type slug.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const POST_TYPE = 'gatherpress_event';

		/**
		 * Constructor for the Setup class.
		 *
		 * Initializes and sets up various components of the plugin.
		 */
		protected function __construct() {
			$this->setup_hooks();
		}

		/**
		 * Set up hooks for various purposes.
		 *
		 * Registers all necessary hooks:
		 * - Monitors post status changes to manage redundant event tracking
		 * - Cleans up tracking before post deletion
		 * - Performs daily checks for ended events that may have been missed
		 * - Schedules the daily cron job if enabled
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		protected function setup_hooks(): void {
			
			if ( ! $this->is_tracker_enabled() ) {
				return;
			}

			// Waiting for post status transitions, post_meta changes or post delete.
			add_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', array( $this, 'add_to_tracking' ) );
			add_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this, 'remove_from_tracking' ) );

			// An event ended regularly, remove it from tracking.
			add_action( Cron_Scheduler::ACTION_HOOK, array( $this, 'remove_from_tracking' ) );
			
			// Schedule the daily check if not already scheduled.
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_event( time(), 'daily', self::CRON_HOOK );
			}
			add_action( self::CRON_HOOK, array( $this, 'validate_events_ended' ) );
		}

		/**
		 * Check if upcoming events option tracker is enabled.
		 *
		 * @since 0.1.0
		 *
		 * @return bool True if upcoming events option tracker is enabled, false otherwise.
		 */
		public function is_tracker_enabled(): bool {
			/**
			 * Filter whether to enable the upcoming events option tracker tracking system.
			 *
			 * The upcoming events option tracker provides redundant tracking of upcoming events and a daily
			 * cron job to catch any events whose scheduled cron jobs failed. This adds
			 * database writes on every event status change, so it's disabled by default.
			 *
			 * Enable for high-value deployments where missing an event cleanup would be critical.
			 *
			 * @since 0.1.0
			 *
			 * @param bool $enabled Whether upcoming events option tracker is enabled. Default false.
			 */
			return apply_filters( 'gatherpress_upcoming_events_option_tracker_enabled', false );
		}


		/**
		 * Daily cron job to check for events that ended but weren't processed.
		 *
		 * If a scheduled cron job fails (server issues,
		 * high load, etc.), this daily check ensures we eventually catch and
		 * process ended events.
		 *
		 * Only runs when the upcoming events option tracker feature is enabled via the
		 * 'gatherpress_upcoming_events_option_tracker_enabled' filter.
		 *
		 * Why daily: Balances thoroughness with resource usage. More frequent
		 * checks would catch events sooner but increase server load. Daily is
		 * reasonable for most use cases.
		 *
		 * Logic flow:
		 * 1. Get all tracked upcoming event IDs from wp_option
		 * 2. Loop through each ID
		 * 3. Check if event has ended using GatherPress's validation
		 * 4. If ended, trigger the normal end handling
		 * 5. Remove from tracking (handled by validate_event_ended)
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function validate_events_ended(): void {

			// Get all tracked event IDs.
			$tracked_ids_raw = get_option( self::OPTION_KEY, array() );
			
			// Ensure we have an array of integers.
			if ( ! is_array( $tracked_ids_raw ) ) {
				return;
			}

			/** 
			 * Cast to int array for type safety
			 * 
			 * @var array<int> $tracked_ids
			 */
			$tracked_ids = array_filter(
				array_map( '\intval', $tracked_ids_raw ),
				function ( $id ) {
					return $id > 0;
				}
			);
			
			// Early return if no events to check.
			if ( empty( $tracked_ids ) ) {
				return;
			}
			
			// Check each tracked event.
			foreach ( $tracked_ids as $event_id ) {
				$event = new Core\Event( $event_id );
				
				if ( ! isset( $event->event ) || self::POST_TYPE !== $event->event->post_type ) {
					// Clean up tracking for non-existent events.
					$this->remove_from_tracking( $event_id );
					continue;
				}
				
				// Check if event has ended.
				// @phpstan-ignore-next-line.
				if ( method_exists( $event, 'has_event_past' ) && $event->has_event_past() ) {

					/**
					 * Trigger the main event end action hook. 
					 *
					 * Central hook for event end processing.
					 * All cleanup operations (cache invalidation, tracking removal, etc.) 
					 * are hooked to this action at various priorities.
					 *
					 * @since 0.1.0
					 * @param int        $event_id The ID of the event that ended.
					 * @param Core\Event $event    The GatherPress event object.
					 */
					do_action( Cron_Scheduler::ACTION_HOOK, $event_id, $event );
				}
			}
		}

		/**
		 * Add an event ID to the tracking list.
		 *
		 * Maintains an array of upcoming event IDs in wp_options. This list serves
		 * as our safety net for catching events whose cron jobs failed.
		 *
		 * Only operates when upcoming events option tracker is enabled.
		 *
		 * @since 0.1.0
		 *
		 * @param int $event_id The event post ID to add to tracking.
		 *
		 * @return void
		 */
		public function add_to_tracking( int $event_id ): void {
			// Get current tracking list.
			$tracked_ids_raw = get_option( self::OPTION_KEY, array() );
			
			// Ensure we have an array.
			if ( ! is_array( $tracked_ids_raw ) ) {
				$tracked_ids_raw = array();
			}

			/** 
			 * Cast to int array for type safety
			 * 
			 * @var array<int> $tracked_ids
			 */
			$tracked_ids = array_map( '\intval', $tracked_ids_raw );
			
			// Add the new ID.
			$tracked_ids[] = $event_id;
			
			// Remove duplicates and re-index.
			$tracked_ids = array_values( array_unique( $tracked_ids ) );
			
			// Save back to database.
			update_option( self::OPTION_KEY, $tracked_ids );
		}

		/**
		 * Remove an event ID from the tracking list.
		 *
		 * Called when an event is unpublished, deleted, or successfully processed.
		 * Keeps the tracking list clean and accurate.
		 *
		 * Only operates when upcoming events option tracker is enabled.
		 *
		 * @since 0.1.0
		 *
		 * @param int $event_id The event post ID to remove from tracking.
		 *
		 * @return void
		 */
		public function remove_from_tracking( int $event_id ): void {
			// Get current tracking list.
			$tracked_ids_raw = get_option( self::OPTION_KEY, array() );
			if ( ! is_array( $tracked_ids_raw ) ) {
				return;
			}

			/** 
			 * Cast to int array for type safety
			 * 
			 * @var array<int> $tracked_ids
			 */
			$tracked_ids = array_map( '\intval', $tracked_ids_raw );
			
			// Cleanly removes all instances of the target ID.
			$tracked_ids = array_diff( $tracked_ids, array( $event_id ) );
			
			// Re-index array to remove gaps.
			$tracked_ids = array_values( $tracked_ids );
			
			// Save back to database.
			update_option( self::OPTION_KEY, $tracked_ids );
		}
	}
}
