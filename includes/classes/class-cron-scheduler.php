<?php
/**
 * Cron Scheduler
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 */

namespace GatherPress_Cache_Invalidation_Hooks;

use GatherPress\Core;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

if ( ! class_exists( 'Cron_Scheduler' ) ) {
	/**
	 * Cron Scheduler
	 *
	 * A singleton class that manages automated scheduling wp cron jobs for GatherPress events,
	 * triggering actions when events end, similar to how WordPress handles scheduled posts.
	 *
	 * This class mirrors WordPress's scheduled post system but operates on event end dates
	 * instead of publication dates. When an event is published, we schedule a cron job for
	 * its end time. When that time arrives, we trigger cache invalidation and custom actions.
	 *
	 * @package GatherPress\Cache_Invalidation_Hooks
	 * @since 0.1.0
	 */
	class Cron_Scheduler {

		use Core\Traits\Singleton;

		/**
		 * The WordPress cron hook name for event end actions.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const ACTION_HOOK = 'gatherpress_event_ended';

		/**
		 * The WordPress cron hook name for event end actions.
		 *
		 * @since 0.1.0
		 * @var string
		 */
		const CRON_HOOK = 'gatherpress_event_ended_cron';

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
		 * Registers all necessary hooks for the scheduler to function:
		 * - Watches for post status changes to manage event scheduling
		 * - Handles the cron event when events end
		 * - Cleans up scheduled events before post deletion
		 * - Hooks cleanup methods to event end action for extensibility
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		protected function setup_hooks(): void {
			add_action( 'transition_post_status', array( $this, 'handle_status_transition' ), 10, 3 );
			add_action( 'before_delete_post', array( $this, 'clear_scheduled_cron' ) );
			add_action( self::CRON_HOOK, array( $this, 'handle_event_ended' ), 10, 1 );
			add_action( self::ACTION_HOOK, array( $this, 'clear_scheduled_cron' ) );
			add_action( self::ACTION_HOOK, array( $this, 'invalidate_caches' ) );
		}

		/**
		 * Handle post status transitions to manage event scheduling.
		 *
		 * This is the core of our scheduling system, mirroring how WordPress handles
		 * scheduled posts. When an event is published, schedules the end action.
		 * When unpublished (draft, private, trash), clears the schedule.
		 *
		 * @since 0.1.0
		 *
		 * @param string   $new_status New post status (e.g., 'publish', 'draft').
		 * @param string   $old_status Previous post status.
		 * @param \WP_Post $post       The post object being transitioned.
		 *
		 * @return void
		 */
		public function handle_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
			if ( self::POST_TYPE !== $post->post_type ) {
				return;
			}

			// Event is being published - schedule the end action.
			if ( 'publish' === $new_status && 'publish' !== $old_status ) {
				$this->add_scheduled_cron( $post->ID );
			}

			// Event is being unpublished - clear the schedule.
			if ( 'publish' === $old_status && 'publish' !== $new_status ) {
				$this->clear_scheduled_cron( $post->ID );
			}
		}

		/**
		 * Handle the event when it ends.
		 *
		 * This is called by WordPress cron when the scheduled time arrives. It performs
		 * final validation and then triggers the gatherpress_event_ended action hook,
		 * which other methods and plugins can hook into for cleanup tasks.
		 *
		 * @since 0.1.0
		 *
		 * @param int $event_id The ID of the event that ended.
		 *
		 * @return void
		 */
		public function handle_event_ended( int $event_id ): void {

			$event = new Core\Event( $event_id );
			
			// Validate the event still exists and is the correct post type.
			if ( ! isset( $event->event ) || self::POST_TYPE !== $event->event->post_type ) {
				return;
			}

			// Ensure the event has past using GatherPress's validation.
			// @phpstan-ignore-next-line.
			if ( ! method_exists( $event, 'has_event_past' ) || ! $event->has_event_past() ) {
				return;
			}

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
			do_action( self::ACTION_HOOK, $event_id, $event );
		}

		/**
		 * Invalidate caches related to the event.
		 *
		 * Clears various cache entries that might include data about this event.
		 * Uses a filterable array of cache keys to allow customization.
		 *
		 * @since 0.1.0
		 *
		 * @param int $event_id The ID of the event to clear caches for.
		 *
		 * @return void
		 */
		public function invalidate_caches( int $event_id ): void {
			// Build array of cache keys to invalidate.
			$default_keys = array(
				"gatherpress_event_{$event_id}",
				'gatherpress_upcoming_events',
				'gatherpress_past_events',
			);

			/**
			 * Filter cache keys to invalidate when an event ends.
			 *
			 * @since 0.1.0
			 *
			 * @param array<string> $cache_keys Array of cache keys to invalidate.
			 * @param int           $event_id   The event ID.
			 */
			$cache_keys = (array) apply_filters(
				'gatherpress_event_end_cache_keys',
				$default_keys,
				$event_id
			);

			// Clear each cache key.
			foreach ( $cache_keys as $key ) {
				// @phpstan-ignore-next-line
				if ( is_string( $key ) && ! empty( $key ) ) {
					wp_cache_delete( $key, 'gatherpress' );
				}
			}

			// Clear WordPress core post caches.
			// Ensures WordPress's internal query caches are also cleared.
			// This affects post queries, term relationships, meta cache, etc.
			clean_post_cache( $event_id );
		}

		/**
		 * Schedule a cron event for when an event ends.
		 *
		 * Reads the event's end date from post meta and schedules a WordPress cron
		 * job to run at that time. Includes validation to ensure we don't schedule
		 * invalid or past dates.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id The event post ID to schedule.
		 *
		 * @return void
		 */
		private function add_scheduled_cron( int $post_id ): void {
			
			$end_date = get_post_meta( $post_id, 'gatherpress_event_end_date', true );
			
			// Validate end date exists and is a string.
			if ( ! is_string( $end_date ) || empty( $end_date ) ) {
				return;
			}

			// Convert date string to Unix timestamp.
			$end_timestamp = strtotime( $end_date );
			
			// Validate timestamp and ensure it's in the future.
			if ( false === $end_timestamp || $end_timestamp <= time() ) {
				return;
			}

			// Clear any existing schedule to prevent duplicates.
			$this->clear_scheduled_cron( $post_id );
			
			// Schedule the cron event.
			wp_schedule_single_event( $end_timestamp, self::CRON_HOOK, array( $post_id ) );
		}

		/**
		 * Clear scheduled cron job for an event.
		 *
		 * Removes any scheduled end-time action for the given event. Called when
		 * events are unpublished or deleted to prevent orphaned cron jobs.
		 *
		 * @since 0.1.0
		 *
		 * @param int $post_id Post ID of the event to clear scheduling for.
		 *
		 * @return void
		 */
		public function clear_scheduled_cron( int $post_id ): void {
			$post = get_post( $post_id );
			
			// Validate post exists and is a GatherPress event.
			if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
				return;
			}

			// Find the timestamp of the next scheduled event.
			$timestamp = wp_next_scheduled( self::CRON_HOOK, array( $post_id ) );
			
			// If an event is scheduled, remove it.
			if ( is_int( $timestamp ) && $timestamp > 0 ) {
				wp_unschedule_event( $timestamp, self::CRON_HOOK, array( $post_id ) );
			}
		}
	}
}
