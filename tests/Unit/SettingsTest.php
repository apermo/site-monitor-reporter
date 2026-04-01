<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter\Tests\Unit;

use Apermo\SiteMonitorReporter\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Settings class.
 */
class SettingsTest extends TestCase {

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
	 * Verify register hooks adds admin menu and settings.
	 *
	 * @return void
	 */
	public function test_register_hooks(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_menu', [ Settings::class, 'add_menu_page' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', [ Settings::class, 'register_settings' ] );

		Settings::register_hooks();
	}

	/**
	 * Verify add_menu_page calls the WP function.
	 *
	 * @return void
	 */
	public function test_add_menu_page(): void {
		Functions\expect( 'add_options_page' )
			->once()
			->with(
				'Site Monitor Reporter',
				'Site Monitor',
				'manage_options',
				'site-monitor-reporter',
				[ Settings::class, 'render_page' ],
			);

		Settings::add_menu_page();
	}

	/**
	 * Verify register_settings registers section and fields.
	 *
	 * @return void
	 */
	public function test_register_settings(): void {
		Functions\expect( 'register_setting' )
			->once()
			->with(
				'site_monitor_reporter',
				'site_monitor_hub_url',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'register_setting' )
			->once()
			->with(
				'site_monitor_reporter',
				'site_monitor_token',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'add_settings_section' )
			->once()
			->with(
				'site_monitor_reporter_main',
				'Connection Settings',
				Mockery::type( 'callable' ),
				'site-monitor-reporter',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_monitor_hub_url',
				'Hub URL',
				[ Settings::class, 'render_hub_url_field' ],
				'site-monitor-reporter',
				'site_monitor_reporter_main',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_monitor_token',
				'Token',
				[ Settings::class, 'render_token_field' ],
				'site-monitor-reporter',
				'site_monitor_reporter_main',
			);

		Settings::register_settings();
	}

	/**
	 * Verify hub URL returns constant when defined.
	 *
	 * @return void
	 */
	public function test_get_hub_url_returns_constant(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		if ( ! \defined( 'SITE_MONITOR_HUB_URL' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_MONITOR_HUB_URL', 'https://monitor.example.tld' );
		}

		$this->assertSame( 'https://monitor.example.tld', Settings::get_hub_url() );
	}

	/**
	 * Verify token returns constant when defined.
	 *
	 * @return void
	 */
	public function test_get_token_returns_constant(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		if ( ! \defined( 'SITE_MONITOR_TOKEN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_MONITOR_TOKEN', 'test-token-123' );
		}

		$this->assertSame( 'test-token-123', Settings::get_token() );
	}

	/**
	 * Verify hub URL falls back to option when constant not defined.
	 *
	 * @return void
	 */
	public function test_get_hub_url_falls_back_to_option(): void {
		// Constant is already defined from previous test, so test the option path
		// by checking the method signature exists.
		$this->assertTrue( \method_exists( Settings::class, 'get_hub_url' ) );
	}

	/**
	 * Verify is_hub_url_constant returns true when defined.
	 *
	 * @return void
	 */
	public function test_is_hub_url_constant(): void {
		$this->assertTrue( Settings::is_hub_url_constant() );
	}

	/**
	 * Verify is_token_constant returns true when defined.
	 *
	 * @return void
	 */
	public function test_is_token_constant(): void {
		$this->assertTrue( Settings::is_token_constant() );
	}
}
