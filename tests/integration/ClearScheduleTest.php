<?php
/**
 * Integration tests for the clear_scheduled_cron method.
 *
 * Tests that scheduled cron jobs are properly removed when events are
 * deleted or their schedule is manually cleared.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;

/**
 * Tests for the clear_scheduled_cron method in the Cron_Scheduler class.
 */
class ClearScheduleTest extends WP_UnitTestCase {

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
	 * Test clear_scheduled_cron ignores non-event post types.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::clear_scheduled_cron
	 */
	public function test_clear_scheduled_cron_ignores_non_events(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		// Manually schedule a cron event for this post.
		wp_schedule_single_event(
			time() + HOUR_IN_SECONDS,
			Cron_Scheduler::CRON_HOOK,
			array( $post_id )
		);

		// clear_scheduled_cron should NOT clear it because it's not a gatherpress_event.
		$this->scheduler->clear_scheduled_cron( $post_id );

		$this->assertIsInt(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'clear_scheduled_cron should not affect non-event post types'
		);

		// Clean up.
		$timestamp = wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Cron_Scheduler::CRON_HOOK, array( $post_id ) );
		}
	}

	/**
	 * Test clear_scheduled_cron handles non-existent posts gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::clear_scheduled_cron
	 */
	public function test_clear_scheduled_cron_handles_nonexistent_post(): void {
		// Should not throw any errors for a non-existent post ID.
		$this->scheduler->clear_scheduled_cron( 999999 );

		// If we get here without errors, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test clear_scheduled_cron removes a scheduled cron job.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::clear_scheduled_cron
	 */
	public function test_clear_scheduled_cron_removes_cron(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Cron_Scheduler::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// Manually schedule.
		wp_schedule_single_event(
			time() + HOUR_IN_SECONDS,
			Cron_Scheduler::CRON_HOOK,
			array( $post_id )
		);

		$this->assertIsInt(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Cron should be scheduled before clearing'
		);

		$this->scheduler->clear_scheduled_cron( $post_id );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Cron should be cleared after calling clear_scheduled_cron'
		);
	}

	/**
	 * Test clear_scheduled_cron handles already-cleared schedule gracefully.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::clear_scheduled_cron
	 */
	public function test_clear_scheduled_cron_when_nothing_scheduled(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Cron_Scheduler::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		// No cron scheduled, should not error.
		$this->scheduler->clear_scheduled_cron( $post_id );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) )
		);
	}
}
