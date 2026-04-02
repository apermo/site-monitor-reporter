<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\Cron;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Cron class.
 */
class CronTest extends TestCase {

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
	 * Verify register_hooks adds wp_version_check action.
	 *
	 * @return void
	 */
	public function test_register_hooks(): void {
		Functions\expect( 'add_action' )
			->once()
			->with(
				'wp_version_check',
				[ Cron::class, 'on_version_check' ],
			);

		Cron::register_hooks();
	}

	/**
	 * Verify schedule sets up cron event.
	 *
	 * @return void
	 */
	public function test_schedule_creates_event(): void {
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'site_bookkeeper_reporter_push' )
			->andReturn( false );

		Functions\expect( 'wp_schedule_event' )
			->once()
			->with(
				Mockery::type( 'int' ),
				'twicedaily',
				'site_bookkeeper_reporter_push',
			);

		Cron::schedule();
	}

	/**
	 * Verify schedule does not duplicate if already scheduled.
	 *
	 * @return void
	 */
	public function test_schedule_skips_if_already_scheduled(): void {
		Functions\expect( 'wp_next_scheduled' )
			->once()
			->with( 'site_bookkeeper_reporter_push' )
			->andReturn( 1234567890 );

		Functions\expect( 'wp_schedule_event' )->never();

		Cron::schedule();
	}

	/**
	 * Verify unschedule clears the cron event.
	 *
	 * @return void
	 */
	public function test_unschedule(): void {
		Functions\expect( 'wp_clear_scheduled_hook' )
			->once()
			->with( 'site_bookkeeper_reporter_push' );

		Cron::unschedule();
	}

	/**
	 * Verify on_network_push returns early if not network-activated.
	 *
	 * @return void
	 */
	public function test_on_network_push_returns_early_without_network(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		// No calls to push should happen.
		Functions\expect( 'wp_remote_post' )->never();

		Cron::on_network_push();

		// If we reach here without error, the method returned early.
		$this->assertTrue( true );
	}

	/**
	 * Verify on_network_push returns early if not main site.
	 *
	 * @return void
	 */
	public function test_on_network_push_returns_early_on_subsite(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );
		Functions\when( 'is_main_site' )->justReturn( false );

		Functions\expect( 'wp_remote_post' )->never();

		Cron::on_network_push();

		$this->assertTrue( true );
	}

	/**
	 * Verify on_version_check is a no-op when network-activated.
	 *
	 * @return void
	 */
	public function test_on_version_check_noop_when_network_activated(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );

		Functions\expect( 'wp_remote_post' )->never();

		Cron::on_version_check();

		$this->assertTrue( true );
	}

	/**
	 * Verify NETWORK_HOOK constant is defined.
	 *
	 * @return void
	 */
	public function test_network_hook_constant(): void {
		$this->assertSame(
			'site_bookkeeper_reporter_network_push',
			Cron::NETWORK_HOOK,
		);
	}
}
