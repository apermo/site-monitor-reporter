<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\NetworkReportPusher;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Tests for the NetworkReportPusher class.
 */
class NetworkReportPusherTest extends TestCase {

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
	 * Verify push returns false without settings.
	 *
	 * @return void
	 */
	public function test_push_returns_false_without_settings(): void {
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		if ( \defined( 'SITE_BOOKKEEPER_HUB_URL' ) && \defined( 'SITE_BOOKKEEPER_TOKEN' ) ) {
			$this->markTestSkipped( 'Constants already defined by another test.' );
		}

		$this->assertFalse( NetworkReportPusher::push() );
	}

	/**
	 * Verify push posts to /network-report endpoint.
	 *
	 * @return void
	 */
	public function test_push_posts_to_network_report_endpoint(): void {
		$this->stub_settings();
		$this->stub_network_collector();

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				'https://monitor.example.tld/network-report',
				Mockery::type( 'array' ),
			)
			->andReturn( [ 'response' => [ 'code' => 200 ] ] );

		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		$this->assertTrue( NetworkReportPusher::push() );
	}

	/**
	 * Verify push sends bearer token.
	 *
	 * @return void
	 */
	public function test_push_sends_bearer_token(): void {
		$this->stub_settings();
		$this->stub_network_collector();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Reading constant.
		$expected_token = \SITE_BOOKKEEPER_TOKEN;

		Functions\expect( 'wp_remote_post' )
			->once()
			->with(
				Mockery::any(),
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

		$this->assertTrue( NetworkReportPusher::push() );
	}

	/**
	 * Verify push returns false on WP_Error.
	 *
	 * @return void
	 */
	public function test_push_returns_false_on_wp_error(): void {
		$this->stub_settings();
		$this->stub_network_collector();

		Functions\when( 'wp_remote_post' )
			->justReturn( Mockery::mock( WP_Error::class ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$this->assertFalse( NetworkReportPusher::push() );
	}

	/**
	 * Verify push returns false on non-2xx response.
	 *
	 * @return void
	 */
	public function test_push_returns_false_on_server_error(): void {
		$this->stub_settings();
		$this->stub_network_collector();

		Functions\when( 'wp_remote_post' )
			->justReturn( [ 'response' => [ 'code' => 500 ] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 500 );

		$this->assertFalse( NetworkReportPusher::push() );
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
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );
	}

	/**
	 * Stub NetworkDataCollector dependencies.
	 *
	 * @return void
	 */
	private function stub_network_collector(): void {
		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );
		Functions\when( 'get_sites' )->justReturn( [] );
		Functions\when( 'get_site_option' )->alias(
			static function ( string $option, mixed $fallback = false ): mixed {
				return $fallback;
			},
		);
		Functions\when( 'get_plugins' )->justReturn( [] );
		Functions\when( 'get_super_admins' )->justReturn( [] );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);
	}
}
