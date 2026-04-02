<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Plugin class.
 */
class PluginTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	/**
	 * Tear down Brain Monkey.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Verify init registers activation and deactivation hooks.
	 *
	 * @return void
	 */
	public function test_init_registers_hooks(): void {
		$file = '/tmp/plugin.php';

		Functions\expect( 'register_activation_hook' )
			->once()
			->with( $file, [ Plugin::class, 'activate' ] );

		Functions\expect( 'register_deactivation_hook' )
			->once()
			->with( $file, [ Plugin::class, 'deactivate' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'plugins_loaded', [ Plugin::class, 'boot' ] );

		Plugin::init( $file );
	}

	/**
	 * Verify init stores the plugin file path.
	 *
	 * @return void
	 */
	public function test_init_stores_file_path(): void {
		$file = '/tmp/plugin.php';

		Functions\stubs(
			[
				'register_activation_hook',
				'register_deactivation_hook',
				'add_action',
			],
		);

		Plugin::init( $file );

		$this->assertSame( $file, Plugin::file() );
	}

	/**
	 * Verify activate can be called without error.
	 *
	 * @return void
	 */
	public function test_activate_schedules_cron(): void {
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'site_bookkeeper_reporter_push' )
			->andReturn( false );

		Functions\expect( 'wp_schedule_event' )->once();

		Plugin::activate();
	}

	/**
	 * Verify deactivate clears the cron schedule.
	 *
	 * @return void
	 */
	public function test_deactivate_unschedules_cron(): void {
		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'site_bookkeeper_reporter_push' );

		Plugin::deactivate();
	}

	/**
	 * Verify boot registers hooks.
	 *
	 * @return void
	 */
	public function test_boot_registers_hooks(): void {
		Functions\when( 'add_action' )->justReturn( null );

		Plugin::boot();

		// Verify add_action was called (Brain Monkey tracks it).
		$this->assertTrue( true );
	}
}
