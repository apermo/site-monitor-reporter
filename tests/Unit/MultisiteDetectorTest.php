<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\MultisiteDetector;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the MultisiteDetector class.
 */
class MultisiteDetectorTest extends TestCase {

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
	 * Verify is_multisite returns true when WP is multisite.
	 *
	 * @return void
	 */
	public function test_is_multisite_returns_true(): void {
		Functions\when( 'is_multisite' )->justReturn( true );

		$this->assertTrue( MultisiteDetector::is_multisite() );
	}

	/**
	 * Verify is_multisite returns false when WP is not multisite.
	 *
	 * @return void
	 */
	public function test_is_multisite_returns_false(): void {
		Functions\when( 'is_multisite' )->justReturn( false );

		$this->assertFalse( MultisiteDetector::is_multisite() );
	}

	/**
	 * Verify is_network_activated returns true when active.
	 *
	 * @return void
	 */
	public function test_is_network_activated_returns_true(): void {
		Functions\when( 'is_plugin_active_for_network' )
			->justReturn( true );

		$this->assertTrue( MultisiteDetector::is_network_activated() );
	}

	/**
	 * Verify is_network_activated returns false when not active.
	 *
	 * @return void
	 */
	public function test_is_network_activated_returns_false(): void {
		Functions\when( 'is_plugin_active_for_network' )
			->justReturn( false );

		$this->assertFalse( MultisiteDetector::is_network_activated() );
	}

	/**
	 * Verify is_main_site returns true on main site.
	 *
	 * @return void
	 */
	public function test_is_main_site_returns_true(): void {
		Functions\when( 'is_main_site' )->justReturn( true );

		$this->assertTrue( MultisiteDetector::is_main_site() );
	}

	/**
	 * Verify is_main_site returns false on subsite.
	 *
	 * @return void
	 */
	public function test_is_main_site_returns_false(): void {
		Functions\when( 'is_main_site' )->justReturn( false );

		$this->assertFalse( MultisiteDetector::is_main_site() );
	}

	/**
	 * Verify get_main_site_url returns network URL on multisite.
	 *
	 * @return void
	 */
	public function test_get_main_site_url_on_multisite(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );

		$this->assertSame(
			'https://network.example.tld/',
			MultisiteDetector::get_main_site_url(),
		);
	}

	/**
	 * Verify get_main_site_url returns site URL on single-site.
	 *
	 * @return void
	 */
	public function test_get_main_site_url_on_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'site_url' )
			->justReturn( 'https://example.tld' );

		$this->assertSame(
			'https://example.tld',
			MultisiteDetector::get_main_site_url(),
		);
	}
}
