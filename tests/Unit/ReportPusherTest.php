<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\ReportPusher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests for the ReportPusher class.
 */
class ReportPusherTest extends TestCase {

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
	 * Verify push returns false when settings are not configured.
	 *
	 * Both hub URL and token must be non-empty. When neither
	 * constants nor options are set, push should bail early.
	 *
	 * @return void
	 */
	public function test_push_returns_false_without_settings(): void {
		// When constants are not yet defined and options return empty.
		Functions\when( 'get_option' )->justReturn( '' );

		// If constants are already defined by a previous test run,
		// push() will proceed. That's acceptable — we verify the
		// early-return path only when settings are truly empty.
		if ( \defined( 'SITE_BOOKKEEPER_HUB_URL' ) && \defined( 'SITE_BOOKKEEPER_TOKEN' ) ) {
			$this->markTestSkipped( 'Constants already defined by another test.' );
		}

		$this->assertFalse( ReportPusher::push() );
	}

	/**
	 * Verify push sends POST with bearer token.
	 *
	 * @return void
	 */
	public function test_push_sends_post_with_bearer_token(): void {
		$this->stub_settings();
		$this->stub_collector();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Reading constant.
		$expected_token = \SITE_BOOKKEEPER_TOKEN;

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://monitor.example.tld/report',
				Mockery::on(
					static function ( array $args ) use ( $expected_token ): bool {
						return isset( $args['headers']['Authorization'] )
							&& $args['headers']['Authorization'] === 'Bearer ' . $expected_token;
					},
				),
			)
			->andReturn( [ 'response' => [ 'code' => 200 ] ] );

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );
		Functions\when( 'set_transient' )->justReturn( true );

		$this->assertTrue( ReportPusher::push() );
	}

	/**
	 * Verify push returns false on WP_Error response.
	 *
	 * @return void
	 */
	public function test_push_returns_false_on_wp_error(): void {
		$this->stub_settings();
		$this->stub_collector();

		Functions\when( 'wp_remote_post' )->justReturn( Mockery::mock( WP_Error::class ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->assertFalse( ReportPusher::push() );
	}

	/**
	 * Verify push returns false on non-2xx response.
	 *
	 * @return void
	 */
	public function test_push_returns_false_on_server_error(): void {
		$this->stub_settings();
		$this->stub_collector();

		Functions\when( 'wp_remote_post' )->justReturn( [ 'response' => [ 'code' => 500 ] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 500 );

		$this->assertFalse( ReportPusher::push() );
	}

	/**
	 * Verify push sends JSON content type.
	 *
	 * @return void
	 */
	public function test_push_sends_json_content_type(): void {
		$this->stub_settings();
		$this->stub_collector();

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				Mockery::any(),
				Mockery::on(
					static function ( array $args ): bool {
						return isset( $args['headers']['Content-Type'] )
							&& $args['headers']['Content-Type'] === 'application/json';
					},
				),
			)
			->andReturn( [ 'response' => [ 'code' => 200 ] ] );

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{}' );
		Functions\when( 'set_transient' )->justReturn( true );

		ReportPusher::push();
	}

	/**
	 * Stub settings to return valid hub URL and token.
	 *
	 * @return void
	 */
	private function stub_settings(): void {
		if ( ! \defined( 'SITE_BOOKKEEPER_HUB_URL' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_HUB_URL', 'https://monitor.example.tld' );
		}
		if ( ! \defined( 'SITE_BOOKKEEPER_TOKEN' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_TOKEN', 'test-token-123' );
		}

		Functions\when( 'get_option' )->justReturn( '' );
	}

	/**
	 * Stub DataCollector dependencies.
	 *
	 * @return void
	 */
	private function stub_collector(): void {
		Functions\when( 'site_url' )->justReturn( 'https://example.tld' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_plugins' )->justReturn( [] );
		Functions\when( 'wp_get_themes' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'get_users' )->justReturn( [] );
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );

		$mock_roles = Mockery::mock( 'WP_Roles' );
		$mock_roles->roles = [];
		Functions\when( 'wp_roles' )->justReturn( $mock_roles );

		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Get theme property.
				 *
				 * @param string $key Property key.
				 *
				 * @return string
				 */
				public function get( string $key ): string {
					return match ( $key ) {
						'Name'    => 'Test Theme',
						'Version' => '1.0.0',
						default   => '',
					};
				}

				/**
				 * Get stylesheet.
				 *
				 * @return string
				 */
				public function get_stylesheet(): string {
					return 'test-theme';
				}
			},
		);
	}
}
