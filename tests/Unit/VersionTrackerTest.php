<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\VersionTracker;
use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the VersionTracker class.
 */
class VersionTrackerTest extends TestCase {

	/**
	 * Set up Brain Monkey.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		VersionTracker::reset();
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
	 * Verify first run sets last_updated to current time.
	 *
	 * @return void
	 */
	public function test_first_run_sets_current_timestamp(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );

		$result = VersionTracker::get_last_updated( 'plugin', 'acf-pro', '6.4.1' );

		$this->assertNotNull( $result );
		// Should be a recent ISO 8601 timestamp.
		$parsed = DateTimeImmutable::createFromFormat( DateTimeInterface::ATOM, $result );
		$this->assertInstanceOf( DateTimeImmutable::class, $parsed );
	}

	/**
	 * Verify unchanged version carries forward previous timestamp.
	 *
	 * @return void
	 */
	public function test_unchanged_version_keeps_previous_timestamp(): void {
		$previous_time = '2026-03-15T10:00:00+00:00';
		$stored        = [
			'plugin:acf-pro' => [
				'version'      => '6.4.1',
				'last_updated' => $previous_time,
			],
		];

		Functions\when( 'get_option' )->justReturn( $stored );
		Functions\when( 'update_option' )->justReturn( true );

		$result = VersionTracker::get_last_updated( 'plugin', 'acf-pro', '6.4.1' );

		$this->assertSame( $previous_time, $result );
	}

	/**
	 * Verify changed version updates timestamp.
	 *
	 * @return void
	 */
	public function test_changed_version_updates_timestamp(): void {
		$stored = [
			'plugin:acf-pro' => [
				'version'      => '6.4.0',
				'last_updated' => '2026-03-10T10:00:00+00:00',
			],
		];

		Functions\when( 'get_option' )->justReturn( $stored );
		Functions\when( 'update_option' )->justReturn( true );

		$result = VersionTracker::get_last_updated( 'plugin', 'acf-pro', '6.4.1' );

		// Should be a new timestamp, not the old one.
		$this->assertNotSame( '2026-03-10T10:00:00+00:00', $result );
	}

	/**
	 * Verify flush persists tracked versions to database.
	 *
	 * @return void
	 */
	public function test_flush_saves_to_database(): void {
		Functions\when( 'get_option' )->justReturn( [] );

		Functions\expect( 'update_option' )
			->once()
			->with( 'site_bookkeeper_version_tracking', Mockery::type( 'array' ), false )
			->andReturn( true );

		VersionTracker::get_last_updated( 'plugin', 'acf-pro', '6.4.1' );
		VersionTracker::flush();
	}

	/**
	 * Verify tracks WordPress core version.
	 *
	 * @return void
	 */
	public function test_tracks_wp_core_version(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );

		$result = VersionTracker::get_last_updated( 'core', 'WordPress', '6.8' );

		$this->assertNotNull( $result );
	}
}
