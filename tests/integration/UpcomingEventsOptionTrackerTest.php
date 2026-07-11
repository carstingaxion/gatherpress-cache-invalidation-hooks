<?php
/**
 * Integration tests for the Option_Tracker class.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since   0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;
use GatherPress_Cache_Invalidation_Hooks\Option_Tracker;

/**
 * Tests for Option_Tracker.
 *
 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
 */
class UpcomingEventsOptionTrackerTest extends WP_UnitTestCase {

	/**
	 * The option tracker instance under test.
	 *
	 * @var Option_Tracker
	 */
	private Option_Tracker $tracker;

	/**
	 * Post types that declare gatherpress-event-date support in this environment.
	 *
	 * @var string[]
	 */
	private array $supporting_types;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->tracker          = Option_Tracker::get_instance();
		$this->supporting_types = get_post_types_by_support( 'gatherpress-event-date' );
		$this->delete_all_tracking_options();
	}

	/**
	 * Tear down — remove filters and clean options.
	 */
	public function tear_down(): void {
		remove_all_filters( 'gatherpress_upcoming_events_option_tracker_enabled' );
		remove_all_filters( 'gatherpress_upcoming_tracker_enabled' );
		foreach ( $this->supporting_types as $pt ) {
			remove_all_filters( "{$pt}_upcoming_tracker_enabled" );
		}
		$this->delete_all_tracking_options();
		parent::tear_down();
	}

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Deletes all per-type tracking options.
	 */
	private function delete_all_tracking_options(): void {
		foreach ( $this->supporting_types as $pt ) {
			delete_option( $this->tracker->option_key_for( $pt ) );
		}
	}

	/**
	 * Returns a supported post type or skips when GatherPress is not active.
	 */
	private function require_event_post_type(): string {
		if ( ! in_array( 'gatherpress_event', $this->supporting_types, true ) ) {
			$this->markTestSkipped( 'gatherpress_event post type not available.' );
		}
		return 'gatherpress_event';
	}

	/**
	 * Seeds the tracking option for a post type with the given IDs.
	 *
	 * @param string $post_type Post type slug.
	 * @param int[]  $ids       IDs to write.
	 */
	private function seed( string $post_type, array $ids ): void {
		update_option( $this->tracker->option_key_for( $post_type ), $ids );
	}

	/**
	 * Reads the tracking option for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return int[]
	 */
	private function tracked( string $post_type ): array {
		return get_option( $this->tracker->option_key_for( $post_type ), array() );
	}

	// ── option_key_for() ──────────────────────────────────────────────────────

	/** Option key follows "upcoming_{PT-slug}s" pattern. */
	public function test_option_key_pattern(): void {
		$this->assertSame( 'upcoming_gatherpress_events', $this->tracker->option_key_for( 'gatherpress_event' ) );
		$this->assertSame( 'upcoming_gatherpress_productions', $this->tracker->option_key_for( 'gatherpress_production' ) );
	}

	// ── is_post_type_enabled() — default off ─────────────────────────────────

	/** Every supporting type is disabled by default. */
	public function test_disabled_by_default_for_all_types(): void {
		foreach ( $this->supporting_types as $pt ) {
			$this->assertFalse( $this->tracker->is_post_type_enabled( $pt ), "Should be disabled by default for {$pt}" );
		}
	}

	// ── is_post_type_enabled() — general filter ───────────────────────────────

	/** General filter enables every supporting post type. */
	public function test_general_filter_enables_all_types(): void {
		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
		foreach ( $this->supporting_types as $pt ) {
			$this->assertTrue( $this->tracker->is_post_type_enabled( $pt ), "General filter should enable {$pt}" );
		}
	}

	/** General filter receives the post type as second argument. */
	public function test_general_filter_receives_post_type_argument(): void {
		$post_type = $this->require_event_post_type();
		$captured  = null;
		add_filter(
			'gatherpress_upcoming_tracker_enabled',
			function ( bool $enabled, string $pt ) use ( &$captured ): bool {
				$captured = $pt;
				return false;
			},
			10,
			2
		);
		$this->tracker->is_post_type_enabled( $post_type );
		$this->assertSame( $post_type, $captured );
	}

	// ── is_post_type_enabled() — per-type filter ─────────────────────────────

	/** Per-type filter enables only its own type. */
	public function test_per_type_filter_enables_only_that_type(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );

		$this->assertTrue( $this->tracker->is_post_type_enabled( $post_type ) );

		foreach ( $this->supporting_types as $other ) {
			if ( $other === $post_type ) {
				continue;
			}
			$this->assertFalse( $this->tracker->is_post_type_enabled( $other ), "Should not enable {$other}" );
		}
	}

	// ── is_post_type_enabled() — deprecated filter ────────────────────────────

	/**
	 * Deprecated filter still enables tracking and fires a deprecation notice
	 * via apply_filters_deprecated() — caught by setExpectedDeprecated().
	 */
	public function test_deprecated_filter_honoured_with_notice(): void {
		$post_type = $this->require_event_post_type();
		add_filter( 'gatherpress_upcoming_events_option_tracker_enabled', '__return_true' );
		$this->setExpectedDeprecated( 'gatherpress_upcoming_events_option_tracker_enabled' );
		$this->assertTrue( $this->tracker->is_post_type_enabled( $post_type ) );
	}

	/** Deprecated filter returning false still fires the deprecation notice. */
	public function test_deprecated_filter_false_still_fires_notice(): void {
		$post_type = $this->require_event_post_type();
		add_filter( 'gatherpress_upcoming_events_option_tracker_enabled', '__return_false' );
		$this->setExpectedDeprecated( 'gatherpress_upcoming_events_option_tracker_enabled' );
		$this->assertFalse( $this->tracker->is_post_type_enabled( $post_type ) );
	}

	// ── any_post_type_enabled() ───────────────────────────────────────────────

	/** Returns false when nothing is enabled. */
	public function test_any_post_type_enabled_default_false(): void {
		$this->assertFalse( $this->tracker->any_post_type_enabled() );
	}

	/** Returns true via the general filter. */
	public function test_any_post_type_enabled_via_general_filter(): void {
		add_filter( 'gatherpress_upcoming_tracker_enabled', '__return_true' );
		$this->assertTrue( $this->tracker->any_post_type_enabled() );
	}

	/** Returns true when a single per-type filter is active. */
	public function test_any_post_type_enabled_via_per_type_filter(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->assertTrue( $this->tracker->any_post_type_enabled() );
	}

	// ── add_to_tracking() ─────────────────────────────────────────────────────

	/** Writes the post ID into the correct per-type option. */
	public function test_add_to_tracking_writes_to_correct_option(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );

		$post = $this->factory()->post->create_and_get(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			) 
		);
		$this->tracker->add_to_tracking( $post->ID, $post );

		$this->assertContains( $post->ID, $this->tracked( $post_type ) );
	}

	/** Is a no-op when the post type is not enabled. */
	public function test_add_to_tracking_skipped_when_disabled(): void {
		$post_type = $this->require_event_post_type();
		$post      = $this->factory()->post->create_and_get(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			) 
		);
		$this->tracker->add_to_tracking( $post->ID, $post );
		$this->assertNotContains( $post->ID, $this->tracked( $post_type ) );
	}

	/** Prevents duplicate entries. */
	public function test_add_to_tracking_prevents_duplicates(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$post = $this->factory()->post->create_and_get(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			) 
		);
		$this->tracker->add_to_tracking( $post->ID, $post );
		$this->tracker->add_to_tracking( $post->ID, $post );
		$this->assertCount( 1, $this->tracked( $post_type ) );
	}

	// ── remove_from_tracking() ────────────────────────────────────────────────

	/** Removes the correct ID from the per-type option. */
	public function test_remove_from_tracking_removes_correct_id(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->seed( $post_type, array( 10, 20, 30 ) );
		$post = new WP_Post(
			(object) array(
				'ID'        => 20,
				'post_type' => $post_type,
			) 
		);
		$this->tracker->remove_from_tracking( 20, $post );
		$tracked = $this->tracked( $post_type );
		$this->assertNotContains( 20, $tracked );
		$this->assertContains( 10, $tracked );
		$this->assertContains( 30, $tracked );
	}

	/** Re-indexes the remaining array keys. */
	public function test_remove_from_tracking_reindexes(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->seed( $post_type, array( 10, 20, 30 ) );
		$post = new WP_Post(
			(object) array(
				'ID'        => 10,
				'post_type' => $post_type,
			) 
		);
		$this->tracker->remove_from_tracking( 10, $post );
		$this->assertSame( array( 0, 1 ), array_keys( $this->tracked( $post_type ) ) );
	}

	/** Is a no-op when the post type is not enabled. */
	public function test_remove_from_tracking_skipped_when_disabled(): void {
		$post_type = $this->require_event_post_type();
		$this->seed( $post_type, array( 10, 20 ) );
		$post = new WP_Post(
			(object) array(
				'ID'        => 10,
				'post_type' => $post_type,
			) 
		);
		$this->tracker->remove_from_tracking( 10, $post );
		$this->assertContains( 10, $this->tracked( $post_type ) );
	}

	/** Falls back to scrubbing all enabled types when post type cannot be resolved. */
	public function test_remove_from_tracking_fallback_scrubs_enabled_types(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->seed( $post_type, array( 99 ) );
		// post ID 99 does not exist → resolve_post_type returns '' → fallback path.
		$this->tracker->remove_from_tracking( 99, 0 );
		$this->assertNotContains( 99, $this->tracked( $post_type ) );
	}

	// ── validate_events_ended() ───────────────────────────────────────────────

	/** Skips disabled post types — their option is left untouched. */
	public function test_validate_events_ended_skips_disabled_types(): void {
		$post_type = $this->require_event_post_type();
		$this->seed( $post_type, array( 999999 ) );
		$this->tracker->validate_events_ended();
		$this->assertContains( 999999, $this->tracked( $post_type ) );
	}

	/** Removes stale IDs for non-existent posts (enabled type). */
	public function test_validate_events_ended_cleans_nonexistent_posts(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->seed( $post_type, array( 999999 ) );
		$this->tracker->validate_events_ended();
		$this->assertNotContains( 999999, $this->tracked( $post_type ) );
	}

	/** Handles an empty tracking list without errors. */
	public function test_validate_events_ended_handles_empty_list(): void {
		$post_type = $this->require_event_post_type();
		add_filter( "{$post_type}_upcoming_tracker_enabled", '__return_true' );
		$this->tracker->validate_events_ended();
		$this->assertTrue( true );
	}

	// ── Hook registration ─────────────────────────────────────────────────────

	/** Remove_from_tracking is always registered on ACTION_HOOK (guard is inside the method). */
	public function test_remove_from_tracking_always_hooked(): void {
		$this->assertNotFalse(
			has_action( Cron_Scheduler::ACTION_HOOK, array( $this->tracker, 'remove_from_tracking' ) ),
			'remove_from_tracking should always be registered on ACTION_HOOK'
		);
	}

	/** Daily cron is not scheduled when no post type is enabled. */
	public function test_daily_cron_not_scheduled_when_all_disabled(): void {
		$this->assertFalse(
			wp_next_scheduled( Option_Tracker::CRON_HOOK ),
			'Daily cron should not be scheduled when no post type is enabled'
		);
	}
}
