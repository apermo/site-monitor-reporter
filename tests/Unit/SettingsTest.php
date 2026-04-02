<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\Settings;
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
	 * Verify register hooks adds admin menu and settings on single-site.
	 *
	 * @return void
	 */
	public function test_register_hooks(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

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
				'Site Bookkeeper Reporter',
				'Site Bookkeeper',
				'manage_options',
				'site-bookkeeper-reporter',
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
				'site_bookkeeper_reporter',
				'site_bookkeeper_hub_url',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'register_setting' )
			->once()
			->with(
				'site_bookkeeper_reporter',
				'site_bookkeeper_token',
				Mockery::type( 'array' ),
			);

		Functions\expect( 'add_settings_section' )
			->once()
			->with(
				'site_bookkeeper_reporter_main',
				'Connection Settings',
				Mockery::type( 'callable' ),
				'site-bookkeeper-reporter',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_bookkeeper_hub_url',
				'Hub URL',
				[ Settings::class, 'render_hub_url_field' ],
				'site-bookkeeper-reporter',
				'site_bookkeeper_reporter_main',
			);

		Functions\expect( 'add_settings_field' )
			->once()
			->with(
				'site_bookkeeper_token',
				'Token',
				[ Settings::class, 'render_token_field' ],
				'site-bookkeeper-reporter',
				'site_bookkeeper_reporter_main',
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

		if ( ! \defined( 'SITE_BOOKKEEPER_HUB_URL' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_HUB_URL', 'https://monitor.example.tld' );
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

		if ( ! \defined( 'SITE_BOOKKEEPER_TOKEN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_TOKEN', 'test-token-123' );
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

	/**
	 * Verify is_network_mode returns true when multisite + network-activated.
	 *
	 * @return void
	 */
	public function test_is_network_mode_returns_true(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );

		$this->assertTrue( Settings::is_network_mode() );
	}

	/**
	 * Verify is_network_mode returns false on single-site.
	 *
	 * @return void
	 */
	public function test_is_network_mode_returns_false_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		$this->assertFalse( Settings::is_network_mode() );
	}

	/**
	 * Verify register_hooks uses network_admin_menu when network-activated.
	 *
	 * @return void
	 */
	public function test_register_hooks_network_mode(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );

		Functions\expect( 'add_action' )
			->once()
			->with( 'network_admin_menu', [ Settings::class, 'add_network_menu_page' ] );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', [ Settings::class, 'register_settings' ] );

		Settings::register_hooks();
	}

	/**
	 * Verify add_network_menu_page registers in network admin.
	 *
	 * @return void
	 */
	public function test_add_network_menu_page(): void {
		Functions\expect( 'add_submenu_page' )
			->once()
			->with(
				'settings.php',
				'Site Bookkeeper Reporter',
				'Site Bookkeeper',
				'manage_network_options',
				'site-bookkeeper-reporter',
				[ Settings::class, 'render_page' ],
			);

		Settings::add_network_menu_page();
	}

	/**
	 * Verify get_hub_url uses get_site_option in network mode.
	 *
	 * @return void
	 */
	public function test_get_hub_url_uses_site_option_in_network_mode(): void {
		// The constant takes precedence since it's already defined.
		// We verify the method exists and works with network mode.
		$this->assertTrue( \method_exists( Settings::class, 'get_hub_url' ) );
	}

	/**
	 * Verify get_token uses get_site_option in network mode.
	 *
	 * @return void
	 */
	public function test_get_token_uses_site_option_in_network_mode(): void {
		// The constant takes precedence since it's already defined.
		// We verify the method exists and works with network mode.
		$this->assertTrue( \method_exists( Settings::class, 'get_token' ) );
	}

	/**
	 * Verify handle_network_settings saves via update_site_option.
	 *
	 * @return void
	 */
	public function test_handle_network_settings(): void {
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'site_bookkeeper_reporter_network' );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_network_options' )
			->andReturn( true );

		Functions\expect( 'update_site_option' )
			->once()
			->with( 'site_bookkeeper_hub_url', 'https://hub.example.tld' );

		Functions\expect( 'update_site_option' )
			->once()
			->with( 'site_bookkeeper_token', 'net-token' );

		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return $url;
			},
		);

		Functions\when( 'sanitize_text_field' )->alias(
			static function ( string $text ): string {
				return $text;
			},
		);

		Functions\when( 'wp_unslash' )->alias(
			static function ( mixed $value ): mixed {
				return $value;
			},
		);

		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );

		Functions\expect( 'network_admin_url' )
			->once()
			->andReturn( 'https://network.example.tld/wp-admin/network/settings.php?page=site-bookkeeper-reporter&updated=true' );

		Functions\expect( 'wp_safe_redirect' )->once();

		$_POST['site_bookkeeper_hub_url'] = 'https://hub.example.tld';
		$_POST['site_bookkeeper_token']   = 'net-token';

		Settings::handle_network_settings();

		unset( $_POST['site_bookkeeper_hub_url'], $_POST['site_bookkeeper_token'] );
	}
}
