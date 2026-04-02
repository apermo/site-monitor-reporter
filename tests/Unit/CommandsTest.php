<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\CLI\Commands;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;
use WP_CLI;
use WP_Error;

/**
 * Tests for the CLI\Commands class.
 *
 * Defines a minimal WP_CLI stub that captures output
 * instead of writing to stdout or exiting on error.
 */
class CommandsTest extends TestCase {

	/**
	 * Set up Brain Monkey and reset WP_CLI output buffer.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		WP_CLI::$logs    = [];
		WP_CLI::$success = '';
		WP_CLI::$error   = '';
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
	 * Verify report errors when settings are missing.
	 *
	 * @return void
	 */
	public function test_report_errors_without_settings(): void {
		Functions\when( 'get_option' )->justReturn( '' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		if ( \defined( 'SITE_BOOKKEEPER_HUB_URL' ) ) {
			$this->markTestSkipped( 'Constants already defined.' );
		}

		$commands = new Commands();
		$commands->report();

		$this->assertStringContainsString( 'must be configured', WP_CLI::$error );
	}

	/**
	 * Verify report succeeds when push returns true.
	 *
	 * @return void
	 */
	public function test_report_success(): void {
		$this->stub_settings();
		$this->stub_collector();

		Functions\when( 'wp_remote_post' )->justReturn( [ 'response' => [ 'code' => 200 ] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$commands = new Commands();
		$commands->report();

		$this->assertStringContainsString( 'successfully', WP_CLI::$success );
	}

	/**
	 * Verify report fails when push returns false.
	 *
	 * @return void
	 */
	public function test_report_failure(): void {
		$this->stub_settings();
		$this->stub_collector();

		Functions\when( 'wp_remote_post' )->justReturn( Mockery::mock( WP_Error::class ) );
		Functions\when( 'is_wp_error' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$commands = new Commands();
		$commands->report();

		$this->assertStringContainsString( 'Failed', WP_CLI::$error );
	}

	/**
	 * Verify status outputs valid JSON by default.
	 *
	 * @return void
	 */
	public function test_status_outputs_json(): void {
		$this->stub_settings();
		$this->stub_collector();

		$commands = new Commands();
		$commands->status( [], [] );

		$output  = \implode( "\n", WP_CLI::$logs );
		$decoded = \json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'schema_version', $decoded );
		$this->assertArrayHasKey( 'environment', $decoded );
		$this->assertArrayHasKey( 'plugins', $decoded );
	}

	/**
	 * Verify status with summary format calls format_items.
	 *
	 * @return void
	 */
	public function test_status_summary_calls_format_items(): void {
		$this->stub_settings();
		$this->stub_collector();

		$format_called = false;

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Test stub.
		Functions\when( 'WP_CLI\\Utils\\format_items' )->alias(
			static function () use ( &$format_called ): void {
				$format_called = true;
			},
		);

		$commands = new Commands();
		$commands->status( [], [ 'format' => 'summary' ] );

		$this->assertTrue( $format_called );
	}

	/**
	 * Verify test command errors when settings are missing.
	 *
	 * @return void
	 */
	public function test_test_errors_without_settings(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		if ( \defined( 'SITE_BOOKKEEPER_HUB_URL' ) ) {
			$this->markTestSkipped( 'Constants already defined.' );
		}

		$commands = new Commands();
		$commands->test();

		$this->assertStringContainsString( 'must be configured', WP_CLI::$error );
	}

	/**
	 * Verify test command succeeds on 200 response.
	 *
	 * @return void
	 */
	public function test_test_connection_success(): void {
		$this->stub_settings();

		Functions\when( 'wp_remote_get' )->justReturn( [ 'response' => [ 'code' => 200 ] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );

		$commands = new Commands();
		$commands->test();

		$this->assertStringContainsString( 'Connection OK', WP_CLI::$success );
		$this->assertStringContainsString( '200', WP_CLI::$success );
	}

	/**
	 * Verify test command reports WP_Error.
	 *
	 * @return void
	 */
	public function test_test_connection_wp_error(): void {
		$this->stub_settings();

		$error = Mockery::mock( WP_Error::class );
		$error->shouldReceive( 'get_error_message' )
			->andReturn( 'Could not resolve host' );

		Functions\when( 'wp_remote_get' )->justReturn( $error );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$commands = new Commands();
		$commands->test();

		$this->assertStringContainsString( 'Connection failed', WP_CLI::$error );
		$this->assertStringContainsString( 'Could not resolve host', WP_CLI::$error );
	}

	/**
	 * Verify test command reports non-2xx response.
	 *
	 * @return void
	 */
	public function test_test_connection_server_error(): void {
		$this->stub_settings();

		Functions\when( 'wp_remote_get' )->justReturn( [ 'response' => [ 'code' => 403 ] ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 403 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( 'Forbidden' );

		$commands = new Commands();
		$commands->test();

		$this->assertStringContainsString( '403', WP_CLI::$error );
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
	 * Verify network-report errors when not on multisite.
	 *
	 * @return void
	 */
	public function test_network_report_errors_without_multisite(): void {
		$this->stub_settings();
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		$commands = new Commands();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- CLI method name.
		$commands->network_report( [], [] );

		$this->assertStringContainsString( 'network-activated', WP_CLI::$error );
	}

	/**
	 * Verify network-status outputs JSON.
	 *
	 * @return void
	 */
	public function test_network_status_outputs_json(): void {
		$this->stub_settings();
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );
		$this->stub_network_collector();

		$commands = new Commands();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- CLI method name.
		$commands->network_status( [], [] );

		$output  = \implode( "\n", WP_CLI::$logs );
		$decoded = \json_decode( $output, true );

		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'schema_version', $decoded );
		$this->assertArrayHasKey( 'main_site_url', $decoded );
	}

	/**
	 * Verify network-status errors without network activation.
	 *
	 * @return void
	 */
	public function test_network_status_errors_without_multisite(): void {
		$this->stub_settings();
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );

		$commands = new Commands();
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- CLI method name.
		$commands->network_status( [], [] );

		$this->assertStringContainsString( 'network-activated', WP_CLI::$error );
	}

	/**
	 * Verify report --all-sites errors without multisite.
	 *
	 * @return void
	 */
	public function test_report_all_sites_errors_without_multisite(): void {
		$this->stub_settings();
		$this->stub_collector();

		$commands = new Commands();
		$commands->report( [], [ 'all-sites' => true ] );

		$this->assertStringContainsString( 'network-activated', WP_CLI::$error );
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
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );
		Functions\when( 'get_site_option' )->justReturn( [] );

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
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);
	}
}
