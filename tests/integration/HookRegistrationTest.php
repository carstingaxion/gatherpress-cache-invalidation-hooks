<?php
/**
 * Integration tests for WordPress hook registrations.
 *
 * Verifies that the scheduler and tracker correctly register all required hooks
 * during initialization.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;
use GatherPress_Cache_Invalidation_Hooks\Option_Tracker;

class HookRegistrationTest extends WP_UnitTestCase {

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
	}

	/**
	 * Test that transition_post_status hook is registered.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_transition_post_status_hook_registered(): void {
		$this->assertIsInt(
			has_action( 'transition_post_status', array( $this->scheduler, 'handle_transition_post_status' ) ),
			'handle_transition_post_status should be hooked to transition_post_status'
		);
	}

	/**
	 * Test that updated_postmeta hook is registered.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_updated_postmeta_hook_registered(): void {
		$this->assertIsInt(
			has_action( 'updated_postmeta', array( $this->scheduler, 'handle_updated_postmeta' ) ),
			'handle_updated_postmeta should be hooked to updated_postmeta'
		);
	}

	/**
	 * Test that before_delete_post hook is registered.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_before_delete_post_hook_registered(): void {
		$this->assertIsInt(
			has_action( 'before_delete_post', array( $this->scheduler, 'handle_before_delete_post' ) ),
			'handle_before_delete_post should be hooked to before_delete_post'
		);
	}

	/**
	 * Test that add_scheduled_cron is hooked to the new upcoming action.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_add_scheduled_cron_hook_registered(): void {
		$this->assertIsInt(
			has_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', array( $this->scheduler, 'add_scheduled_cron' ) ),
			'add_scheduled_cron should be hooked to gatherpress_cache_invalidation_hooks_new_upcoming'
		);
	}

	/**
	 * Test that clear_scheduled_cron is hooked to the clear action.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_clear_scheduled_cron_hook_registered_on_clear(): void {
		$this->assertIsInt(
			has_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this->scheduler, 'clear_scheduled_cron' ) ),
			'clear_scheduled_cron should be hooked to gatherpress_cache_invalidation_hooks_clear'
		);
	}

	/**
	 * Test that invalidate_caches is hooked to the clear action.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_invalidate_caches_hook_registered_on_clear(): void {
		$this->assertIsInt(
			has_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this->scheduler, 'invalidate_caches' ) ),
			'invalidate_caches should be hooked to gatherpress_cache_invalidation_hooks_clear'
		);
	}

	/**
	 * Test that validate_event_ended is hooked to the CRON_HOOK.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_validate_event_ended_cron_hook_registered(): void {
		$this->assertIsInt(
			has_action( Cron_Scheduler::CRON_HOOK, array( $this->scheduler, 'validate_event_ended' ) ),
			'validate_event_ended should be hooked to ' . Cron_Scheduler::CRON_HOOK
		);
	}

	/**
	 * Test that clear_scheduled_cron is hooked to the ACTION_HOOK (event ended).
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_clear_scheduled_cron_hook_registered_on_event_ended(): void {
		$this->assertIsInt(
			has_action( Cron_Scheduler::ACTION_HOOK, array( $this->scheduler, 'clear_scheduled_cron' ) ),
			'clear_scheduled_cron should be hooked to ' . Cron_Scheduler::ACTION_HOOK
		);
	}

	/**
	 * Test that invalidate_caches is hooked to the ACTION_HOOK (event ended).
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_invalidate_caches_hook_registered_on_event_ended(): void {
		$this->assertIsInt(
			has_action( Cron_Scheduler::ACTION_HOOK, array( $this->scheduler, 'invalidate_caches' ) ),
			'invalidate_caches should be hooked to ' . Cron_Scheduler::ACTION_HOOK
		);
	}

	/**
	 * Test that the plugin setup function is registered on plugins_loaded.
	 *
	 * @covers ::gatherpress_cache_invalidation_hooks_setup
	 */
	public function test_setup_hook_registered(): void {
		$this->assertIsInt(
			has_action( 'plugins_loaded', 'gatherpress_cache_invalidation_hooks_setup' ),
			'Plugin setup function should be hooked to plugins_loaded'
		);
	}

	/**
	 * Test that daily tracker cron is NOT scheduled by default.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_daily_tracker_cron_not_scheduled_by_default(): void {
		$this->assertFalse(
			wp_next_scheduled( Option_Tracker::CRON_HOOK ),
			'Daily tracker cron should not be scheduled when feature is disabled'
		);
	}

	/**
	 * Test that tracker hooks are NOT registered when feature is disabled.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_tracker_hooks_not_registered_when_disabled(): void {
		$this->assertFalse(
			has_action( 'gatherpress_cache_invalidation_hooks_new_upcoming', array( $this->tracker, 'add_to_tracking' ) ),
			'add_to_tracking should not be hooked when tracker is disabled'
		);

		$this->assertFalse(
			has_action( 'gatherpress_cache_invalidation_hooks_clear', array( $this->tracker, 'remove_from_tracking' ) ),
			'remove_from_tracking should not be hooked to clear action when tracker is disabled'
		);

		$this->assertFalse(
			has_action( Cron_Scheduler::ACTION_HOOK, array( $this->tracker, 'remove_from_tracking' ) ),
			'remove_from_tracking should not be hooked to event ended when tracker is disabled'
		);

		$this->assertFalse(
			has_action( Option_Tracker::CRON_HOOK, array( $this->tracker, 'validate_events_ended' ) ),
			'validate_events_ended should not be hooked when tracker is disabled'
		);
	}

	/**
	 * Test that the deactivation hook is registered.
	 *
	 * @covers ::gatherpress_cache_invalidation_hooks_deactivate
	 */
	public function test_deactivation_hook_registered(): void {
		// The deactivation hook is registered via register_deactivation_hook(),
		// which internally uses the 'deactivate_' . plugin_basename() action.
		// We verify the function exists instead.
		$this->assertTrue(
			function_exists( 'gatherpress_cache_invalidation_hooks_deactivate' ),
			'Deactivation function should be defined'
		);
	}
}