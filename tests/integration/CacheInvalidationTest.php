<?php
/**
 * Integration tests for cache invalidation.
 *
 * Tests that cache keys are properly invalidated when events end,
 * and that the filter for custom cache keys works correctly.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;

/**
 * Tests for cache invalidation functionality in the Cron_Scheduler class.
 */
class CacheInvalidationTest extends WP_UnitTestCase {

	/**
	 * The scheduler instance.
	 *
	 * @var Cron_Scheduler
	 */
	private Cron_Scheduler $scheduler;

	/**
	 * Set up test fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->scheduler = Cron_Scheduler::get_instance();
	}

	/**
	 * Test that invalidate_caches clears object cache entries.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::invalidate_caches
	 */
	public function test_invalidate_caches_clears_object_cache(): void {
		$post_id = 42;

		// Set cache values.
		wp_cache_set( "gatherpress_event_{$post_id}", 'cached_data', 'gatherpress' );
		wp_cache_set( 'gatherpress_upcoming_events', 'cached_list', 'gatherpress' );
		wp_cache_set( 'gatherpress_past_events', 'cached_list', 'gatherpress' );

		// Verify caches are set.
		$this->assertEquals( 'cached_data', wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ) );

		$this->scheduler->invalidate_caches( $post_id );

		// Verify caches are cleared.
		$this->assertFalse(
			wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ),
			'Event-specific cache should be cleared'
		);
		$this->assertFalse(
			wp_cache_get( 'gatherpress_upcoming_events', 'gatherpress' ),
			'Upcoming events cache should be cleared'
		);
		$this->assertFalse(
			wp_cache_get( 'gatherpress_past_events', 'gatherpress' ),
			'Past events cache should be cleared'
		);
	}

	/**
	 * Test that the cache keys filter works.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::invalidate_caches
	 */
	public function test_cache_keys_filter(): void {
		$post_id = 42;

		// Add a custom cache key via filter.
		$filter_callback = function ( $keys, $event_id ) {
			$keys[] = "custom_cache_{$event_id}";
			return $keys;
		};
		add_filter( 'gatherpress_event_end_cache_keys', $filter_callback, 10, 2 );

		// Set the custom cache.
		wp_cache_set( "custom_cache_{$post_id}", 'custom_data', 'gatherpress' );

		$this->scheduler->invalidate_caches( $post_id );

		// Verify custom cache is also cleared.
		$this->assertFalse(
			wp_cache_get( "custom_cache_{$post_id}", 'gatherpress' ),
			'Custom cache key from filter should be cleared'
		);

		// Clean up.
		remove_filter( 'gatherpress_event_end_cache_keys', $filter_callback );
	}

	/**
	 * Test that invalidate_caches handles non-array filter return gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::invalidate_caches
	 */
	public function test_cache_invalidation_handles_bad_filter_return(): void {
		$post_id = 42;

		// Add a filter that returns a non-array (bad behavior from another plugin).
		$filter_callback = function () {
			return 'not_an_array';
		};
		add_filter( 'gatherpress_event_end_cache_keys', $filter_callback );

		// Set default cache.
		wp_cache_set( "gatherpress_event_{$post_id}", 'cached_data', 'gatherpress' );

		// Should not throw, should fall back to defaults via (array) cast.
		$this->scheduler->invalidate_caches( $post_id );

		// Default keys should still be cleared.
		$this->assertFalse(
			wp_cache_get( "gatherpress_event_{$post_id}", 'gatherpress' ),
			'Default cache should be cleared even when filter returns bad data'
		);

		// Clean up.
		remove_filter( 'gatherpress_event_end_cache_keys', $filter_callback );
	}

	/**
	 * Test that invalidate_caches is hooked to the event ended action.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_invalidate_caches_is_hooked_to_event_ended(): void {
		$this->assertIsInt(
			has_action( Cron_Scheduler::ACTION_HOOK, array( $this->scheduler, 'invalidate_caches' ) ),
			'invalidate_caches should be hooked to ' . Cron_Scheduler::ACTION_HOOK
		);
	}

	/**
	 * Test that invalidate_caches is hooked to the clear action.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_invalidate_caches_is_hooked_to_clear(): void {
		$this->assertIsInt(
			has_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this->scheduler, 'invalidate_caches' ) ),
			'invalidate_caches should be hooked to gatherpress_cache_invalidation_hooks_clear'
		);
	}
}
