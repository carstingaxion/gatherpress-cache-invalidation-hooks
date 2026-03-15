<?php
/**
 * Unit tests for the Cron_Scheduler and Option_Tracker classes.
 *
 * These tests validate the singleton pattern (via GatherPress Core\Traits\Singleton),
 * class constants, and method signatures. Since the classes depend on WordPress hooks
 * and GatherPress Core in their constructors, we run these within the WP test suite.
 *
 * @package GatherPress\Cache_Invalidation_Hooks
 * @since 0.1.0
 */

use GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler;
use GatherPress_Cache_Invalidation_Hooks\Option_Tracker;

/**
 * Tests for the Cron_Scheduler and Option_Tracker classes.
 */
class SchedulerClassTest extends WP_UnitTestCase {

	/**
	 * Test that the Cron_Scheduler class exists after plugin load.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_cron_scheduler_class_exists(): void {
		$this->assertTrue( class_exists( Cron_Scheduler::class ) );
	}

	/**
	 * Test that the Option_Tracker class exists after plugin load.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_option_tracker_class_exists(): void {
		$this->assertTrue( class_exists( Option_Tracker::class ) );
	}

	/**
	 * Test the Cron_Scheduler singleton pattern returns the same instance.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::get_instance
	 */
	public function test_cron_scheduler_singleton_returns_same_instance(): void {
		$instance_a = Cron_Scheduler::get_instance();
		$instance_b = Cron_Scheduler::get_instance();

		$this->assertSame( $instance_a, $instance_b );
	}

	/**
	 * Test the Option_Tracker singleton pattern returns the same instance.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::get_instance
	 */
	public function test_option_tracker_singleton_returns_same_instance(): void {
		$instance_a = Option_Tracker::get_instance();
		$instance_b = Option_Tracker::get_instance();

		$this->assertSame( $instance_a, $instance_b );
	}

	/**
	 * Test that get_instance returns an instance of the correct class.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::get_instance
	 */
	public function test_cron_scheduler_get_instance_returns_correct_type(): void {
		$instance = Cron_Scheduler::get_instance();

		$this->assertInstanceOf( Cron_Scheduler::class, $instance );
	}

	/**
	 * Test that get_instance returns an instance of the correct class.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::get_instance
	 */
	public function test_option_tracker_get_instance_returns_correct_type(): void {
		$instance = Option_Tracker::get_instance();

		$this->assertInstanceOf( Option_Tracker::class, $instance );
	}

	/**
	 * Test that the Cron_Scheduler constructor is protected (Singleton trait pattern).
	 *
	 * GatherPress Core\Traits\Singleton uses a protected constructor.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_cron_scheduler_constructor_is_protected(): void {
		$reflection  = new ReflectionClass( Cron_Scheduler::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor );
		$this->assertTrue( $constructor->isProtected() );
	}

	/**
	 * Test that the Option_Tracker constructor is protected (Singleton trait pattern).
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_option_tracker_constructor_is_protected(): void {
		$reflection  = new ReflectionClass( Option_Tracker::class );
		$constructor = $reflection->getConstructor();

		$this->assertNotNull( $constructor );
		$this->assertTrue( $constructor->isProtected() );
	}

	/**
	 * Test that all expected public methods exist on Cron_Scheduler.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_cron_scheduler_public_methods_exist(): void {
		$expected_methods = array(
			'get_instance',
			'handle_transition_post_status',
			'handle_updated_postmeta',
			'handle_before_delete_post',
			'validate_event_ended',
			'add_scheduled_cron',
			'clear_scheduled_cron',
			'invalidate_caches',
		);

		foreach ( $expected_methods as $method ) {
			$this->assertTrue(
				method_exists( Cron_Scheduler::class, $method ),
				"Method {$method} does not exist on Cron_Scheduler"
			);
		}
	}

	/**
	 * Test that all expected public methods exist on Option_Tracker.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_option_tracker_public_methods_exist(): void {
		$expected_methods = array(
			'get_instance',
			'is_tracker_enabled',
			'add_to_tracking',
			'remove_from_tracking',
			'validate_events_ended',
		);

		foreach ( $expected_methods as $method ) {
			$this->assertTrue(
				method_exists( Option_Tracker::class, $method ),
				"Method {$method} does not exist on Option_Tracker"
			);
		}
	}

	/**
	 * Test that handle_transition_post_status has correct parameter types.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_transition_post_status
	 */
	public function test_handle_transition_post_status_signature(): void {
		$reflection = new ReflectionMethod( Cron_Scheduler::class, 'handle_transition_post_status' );
		$params     = $reflection->getParameters();

		$this->assertCount( 3, $params );
		$this->assertEquals( 'new_status', $params[0]->getName() );
		$this->assertEquals( 'old_status', $params[1]->getName() );
		$this->assertEquals( 'post', $params[2]->getName() );

		// Check type hints.
		$this->assertEquals( 'string', $params[0]->getType()->getName() );
		$this->assertEquals( 'string', $params[1]->getType()->getName() );
		$this->assertEquals( 'WP_Post', $params[2]->getType()->getName() );
	}

	/**
	 * Test that validate_event_ended accepts an integer parameter.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::validate_event_ended
	 */
	public function test_validate_event_ended_signature(): void {
		$reflection = new ReflectionMethod( Cron_Scheduler::class, 'validate_event_ended' );
		$params     = $reflection->getParameters();

		$this->assertCount( 1, $params );
		$this->assertEquals( 'event_id', $params[0]->getName() );
		$this->assertEquals( 'int', $params[0]->getType()->getName() );
	}

	/**
	 * Test that handle_updated_postmeta has correct parameter types.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler::handle_updated_postmeta
	 */
	public function test_handle_updated_postmeta_signature(): void {
		$reflection = new ReflectionMethod( Cron_Scheduler::class, 'handle_updated_postmeta' );
		$params     = $reflection->getParameters();

		$this->assertCount( 3, $params );
		$this->assertEquals( 'meta_id', $params[0]->getName() );
		$this->assertEquals( 'object_id', $params[1]->getName() );
		$this->assertEquals( 'meta_key', $params[2]->getName() );

		$this->assertEquals( 'int', $params[0]->getType()->getName() );
		$this->assertEquals( 'int', $params[1]->getType()->getName() );
		$this->assertEquals( 'string', $params[2]->getType()->getName() );
	}

	/**
	 * Test that return types are void where expected on Cron_Scheduler.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_cron_scheduler_void_return_types(): void {
		$void_methods = array(
			'handle_transition_post_status',
			'handle_updated_postmeta',
			'handle_before_delete_post',
			'validate_event_ended',
			'add_scheduled_cron',
			'clear_scheduled_cron',
			'invalidate_caches',
		);

		foreach ( $void_methods as $method_name ) {
			$reflection  = new ReflectionMethod( Cron_Scheduler::class, $method_name );
			$return_type = $reflection->getReturnType();

			$this->assertNotNull( $return_type, "Method {$method_name} should have a return type" );
			$this->assertEquals( 'void', $return_type->getName(), "Method {$method_name} should return void" );
		}
	}

	/**
	 * Test that return types are void where expected on Option_Tracker.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_option_tracker_void_return_types(): void {
		$void_methods = array(
			'add_to_tracking',
			'remove_from_tracking',
			'validate_events_ended',
		);

		foreach ( $void_methods as $method_name ) {
			$reflection  = new ReflectionMethod( Option_Tracker::class, $method_name );
			$return_type = $reflection->getReturnType();

			$this->assertNotNull( $return_type, "Method {$method_name} should have a return type" );
			$this->assertEquals( 'void', $return_type->getName(), "Method {$method_name} should return void" );
		}
	}

	/**
	 * Test that is_tracker_enabled returns bool.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::is_tracker_enabled
	 */
	public function test_option_tracker_is_tracker_enabled_return_type(): void {
		$reflection  = new ReflectionMethod( Option_Tracker::class, 'is_tracker_enabled' );
		$return_type = $reflection->getReturnType();

		$this->assertNotNull( $return_type );
		$this->assertEquals( 'bool', $return_type->getName() );
	}

	/**
	 * Test that class constants are defined correctly on Cron_Scheduler.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Cron_Scheduler
	 */
	public function test_cron_scheduler_constants(): void {
		$this->assertEquals( 'gatherpress_event_ended', Cron_Scheduler::ACTION_HOOK );
		$this->assertEquals( 'gatherpress_event_ended_cron', Cron_Scheduler::CRON_HOOK );
		$this->assertEquals( 'gatherpress_event', Cron_Scheduler::POST_TYPE );
		$this->assertEquals( 'gatherpress_datetime_end_gmt', Cron_Scheduler::POST_META_KEY );
	}

	/**
	 * Test that class constants are defined correctly on Option_Tracker.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker
	 */
	public function test_option_tracker_constants(): void {
		$this->assertEquals( 'gatherpress_validate_events_ended', Option_Tracker::CRON_HOOK );
		$this->assertEquals( 'gatherpress_upcoming_events', Option_Tracker::OPTION_KEY );
		$this->assertEquals( 'gatherpress_event', Option_Tracker::POST_TYPE );
	}

	/**
	 * Test that is_tracker_enabled returns false by default.
	 *
	 * @covers \GatherPress_Cache_Invalidation_Hooks\Option_Tracker::is_tracker_enabled
	 */
	public function test_option_tracker_disabled_by_default(): void {
		$tracker = Option_Tracker::get_instance();

		$this->assertFalse( $tracker->is_tracker_enabled() );
	}
}
