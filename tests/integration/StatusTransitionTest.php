<?php
/**
 * Integration tests for event status transitions.
 *
 * Tests the scheduling and unscheduling of cron jobs when GatherPress events
 * change status. Uses the WordPress test suite to create real posts and
 * verify cron behavior.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;

/**
 * Tests for event status transitions and their impact on cron scheduling.
 */
class StatusTransitionTest extends WP_UnitTestCase {

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
	 * Create a test event post with an end date.
	 *
	 * Uses the actual GatherPress post type and the correct post meta key
	 * (gatherpress_datetime_end_gmt) that the scheduler reads.
	 *
	 * @param string $end_date Optional. The event end date in GMT. Default is 1 hour from now.
	 * @param string $status   Optional. The post status. Default 'draft'.
	 * @return int The created post ID.
	 */
	private function create_test_event( string $end_date = '', string $status = 'draft' ): int {
		if ( empty( $end_date ) ) {
			$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		}

		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Cron_Scheduler::POST_TYPE,
				'post_status' => $status,
				'post_title'  => 'Test Event',
			)
		);

		update_post_meta( $post_id, Cron_Scheduler::POST_META_KEY, $end_date );

		return $post_id;
	}

	/**
	 * Test that handle_transition_post_status ignores non-event post types.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_ignores_non_event_post_types(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'draft',
			)
		);

		$post = get_post( $post_id );

		// Transition to publish - should NOT schedule anything.
		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Should not schedule cron for non-event post types'
		);
	}

	/**
	 * Test that publishing an event schedules a cron job.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_publish_schedules_cron(): void {
		$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id  = $this->create_test_event( $end_date, 'draft' );
		$post     = get_post( $post_id );

		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );

		$timestamp = wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) );

		$this->assertIsInt( $timestamp, 'Cron job should be scheduled after publishing' );
		$this->assertGreaterThan( time(), $timestamp, 'Scheduled time should be in the future' );
	}

	/**
	 * Test that unpublishing an event clears the cron job.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_unpublish_clears_cron(): void {
		$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id  = $this->create_test_event( $end_date, 'draft' );
		$post     = get_post( $post_id );

		// First publish to schedule.
		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );
		$this->assertIsInt(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Cron should be scheduled after publish'
		);

		// Then unpublish to clear.
		$this->scheduler->handle_transition_post_status( 'draft', 'publish', $post );
		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Cron should be cleared after unpublishing'
		);
	}

	/**
	 * Test that trashing an event clears the cron job.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_trash_clears_cron(): void {
		$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id  = $this->create_test_event( $end_date, 'draft' );
		$post     = get_post( $post_id );

		// Publish then trash.
		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );
		$this->scheduler->handle_transition_post_status( 'trash', 'publish', $post );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Cron should be cleared after trashing'
		);
	}

	/**
	 * Test that republishing reschedules the cron job.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_republish_reschedules_cron(): void {
		$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id  = $this->create_test_event( $end_date, 'draft' );
		$post     = get_post( $post_id );

		// Publish, draft, publish.
		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );
		$this->scheduler->handle_transition_post_status( 'draft', 'publish', $post );
		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );

		$timestamp = wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) );

		$this->assertIsInt( $timestamp, 'Cron should be rescheduled after republishing' );
	}

	/**
	 * Test that events with past end dates are not scheduled.
	 *
	 * The is_valid_future_event check should prevent scheduling for past events.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_past_end_date_not_scheduled(): void {
		$past_date = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$post_id   = $this->create_test_event( $past_date, 'draft' );
		$post      = get_post( $post_id );

		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Events with past end dates should not be scheduled'
		);
	}

	/**
	 * Test that events without end dates are not scheduled.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_missing_end_date_not_scheduled(): void {
		$post_id = $this->factory()->post->create(
			array(
				'post_type'   => Cron_Scheduler::POST_TYPE,
				'post_status' => 'draft',
			)
		);
		// Deliberately NOT setting end date meta.

		$post = get_post( $post_id );

		$this->scheduler->handle_transition_post_status( 'publish', 'draft', $post );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Events without end dates should not be scheduled'
		);
	}

	/**
	 * Test that same status transitions are ignored (publish to publish).
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_same_status_transition_ignored(): void {
		$end_date = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
		$post_id  = $this->create_test_event( $end_date, 'publish' );
		$post     = get_post( $post_id );

		// publish to publish should not schedule (only non-publish to publish does).
		$this->scheduler->handle_transition_post_status( 'publish', 'publish', $post );

		$this->assertFalse(
			wp_next_scheduled( Cron_Scheduler::CRON_HOOK, array( $post_id ) ),
			'Same status transitions should not trigger scheduling'
		);
	}
}
