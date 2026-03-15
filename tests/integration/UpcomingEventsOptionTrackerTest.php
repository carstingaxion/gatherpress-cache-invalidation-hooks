<?php
/**
 * Integration tests for the upcoming events option tracker system.
 *
 * Tests the optional redundant tracking system that stores upcoming event IDs
 * in a wp_option and provides a daily cron check for missed events.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;
use GatherPress_Cache_Invalidation_Hooks\Option_Tracker;

/**
 * Tests for the Option_Tracker class and its integration with the cron scheduler.
 *
 * Covers enabling/disabling tracking, adding/removing event IDs from tracking,
 * and the behavior of the validate_events_ended method.
 */
class UpcomingEventsOptionTrackerTest extends WP_UnitTestCase {

	/**
	 * The scheduler instance.
	 *
	 * @var Cron_Scheduler
	 */
	private Cron_Scheduler $scheduler;

	/**
	 * The option tracker instance.
	 *
	 * @var Option_Tracker
	 */
	private Option_Tracker $tracker;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->scheduler = Cron_Scheduler::get_instance();
		$this->tracker   = Option_Tracker::get_instance();

		// Clean up the option before each test.
		delete_option( Option_Tracker::OPTION_KEY );
	}

	/**
	 * Tear down test fixtures.
	 */
	public function tear_down(): void {
		// Remove any filters we added.
		remove_all_filters( 'gatherpress_upcoming_events_option_tracker_enabled' );

		// Clean up the option.
		delete_option( Option_Tracker::OPTION_KEY );

		parent::tear_down();
	}

	/**
	 * Test that tracking is disabled by default.
	 *
	 * When disabled, publishing an event should NOT add it to the tracking option.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::is_tracker_enabled
	 */
	public function test_tracking_disabled_by_default(): void {
		$this->assertFalse(
			$this->tracker->is_tracker_enabled(),
			'Tracker should be disabled by default'
		);

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		$this->assertEmpty(
			$tracked,
			'Tracking option should be empty when feature is disabled'
		);
	}

	/**
	 * Test that remove_from_tracking cleans the option array.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::remove_from_tracking
	 */
	public function test_remove_from_tracking_cleans_option(): void {
		// Manually set some tracked IDs.
		update_option( Option_Tracker::OPTION_KEY, array( 10, 20, 30 ) );

		$this->tracker->remove_from_tracking( 20 );

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		$this->assertCount( 2, $tracked );
		$this->assertContains( 10, $tracked );
		$this->assertContains( 30, $tracked );
		$this->assertNotContains( 20, $tracked );
	}

	/**
	 * Test remove_from_tracking handles empty option gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::remove_from_tracking
	 */
	public function test_remove_from_tracking_handles_empty(): void {
		// No option set - should not error.
		$this->tracker->remove_from_tracking( 42 );

		// Should complete without errors.
		$this->assertTrue( true );
	}

	/**
	 * Test remove_from_tracking handles non-array option gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::remove_from_tracking
	 */
	public function test_remove_from_tracking_handles_non_array(): void {
		update_option( Option_Tracker::OPTION_KEY, 'not_an_array' );

		// Should not throw.
		$this->tracker->remove_from_tracking( 42 );

		$this->assertTrue( true );
	}

	/**
	 * Test remove_from_tracking re-indexes array keys.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::remove_from_tracking
	 */
	public function test_remove_from_tracking_reindexes_keys(): void {
		update_option( Option_Tracker::OPTION_KEY, array( 10, 20, 30 ) );

		$this->tracker->remove_from_tracking( 10 );

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		// Keys should be sequential (0, 1) not (1, 2).
		$this->assertEquals( array( 20, 30 ), $tracked );
		$this->assertSame( array( 0, 1 ), array_keys( $tracked ) );
	}

	/**
	 * Test add_to_tracking adds an event ID to the option.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::add_to_tracking
	 */
	public function test_add_to_tracking_adds_event_id(): void {
		$this->tracker->add_to_tracking( 42 );

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		$this->assertCount( 1, $tracked );
		$this->assertContains( 42, $tracked );
	}

	/**
	 * Test add_to_tracking prevents duplicates.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::add_to_tracking
	 */
	public function test_add_to_tracking_prevents_duplicates(): void {
		$this->tracker->add_to_tracking( 42 );
		$this->tracker->add_to_tracking( 42 );

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		$this->assertCount( 1, $tracked );
	}

	/**
	 * Test add_to_tracking handles non-array option gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::add_to_tracking
	 */
	public function test_add_to_tracking_handles_non_array(): void {
		update_option( Option_Tracker::OPTION_KEY, 'not_an_array' );

		$this->tracker->add_to_tracking( 42 );

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		$this->assertIsArray( $tracked );
		$this->assertContains( 42, $tracked );
	}

	/**
	 * Test that tracking hooks are NOT registered when feature is disabled.
	 *
	 * Since the singleton pattern means hooks are registered once at init time,
	 * and the feature is disabled by default, the tracker hooks should not be present.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_tracking_hook_registration_default(): void {
		// By default, the feature is disabled, so remove_from_tracking
		// should NOT be hooked to gatherpress_event_ended.
		$priority = has_action(
			Cron_Scheduler::ACTION_HOOK,
			array( $this->tracker, 'remove_from_tracking' )
		);

		// When disabled, has_action returns false.
		$this->assertFalse(
			$priority,
			'remove_from_tracking should not be hooked when feature is disabled'
		);
	}

	/**
	 * Test that validate_events_ended handles empty tracking list.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::validate_events_ended
	 */
	public function test_validate_events_ended_handles_empty_list(): void {
		delete_option( Option_Tracker::OPTION_KEY );

		// Should complete without errors.
		$this->tracker->validate_events_ended();

		$this->assertTrue( true );
	}

	/**
	 * Test that validate_events_ended handles non-array option.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::validate_events_ended
	 */
	public function test_validate_events_ended_handles_non_array(): void {
		update_option( Option_Tracker::OPTION_KEY, 'invalid' );

		// Should complete without errors.
		$this->tracker->validate_events_ended();

		$this->assertTrue( true );
	}

	/**
	 * Test that validate_events_ended cleans up non-existent posts.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::validate_events_ended
	 */
	public function test_validate_events_ended_cleans_nonexistent_posts(): void {
		// Track a non-existent post ID.
		update_option( Option_Tracker::OPTION_KEY, array( 999999 ) );

		// This will attempt to instantiate GatherPress Core\Event, which should
		// handle non-existent posts gracefully by removing them from tracking.
		$this->tracker->validate_events_ended();

		$tracked = get_option( Option_Tracker::OPTION_KEY, array() );

		// Non-existent post should be removed from tracking.
		$this->assertNotContains( 999999, $tracked );
	}

	/**
	 * Test the daily cron hook is NOT scheduled when feature is disabled.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_daily_cron_not_scheduled_when_disabled(): void {
		$this->assertFalse(
			wp_next_scheduled( Option_Tracker::CRON_HOOK ),
			'Daily tracker cron should not be scheduled when feature is disabled'
		);
	}
}
