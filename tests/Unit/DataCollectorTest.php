<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\DataCollector;
use Apermo\SiteBookkeeperReporter\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeInterface;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DataCollector class.
 */
class DataCollectorTest extends TestCase {

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
	 * Verify collect returns array with required top-level keys.
	 *
	 * @return void
	 */
	public function test_collect_returns_required_keys(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$this->assertArrayHasKey( 'schema_version', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'site_url', $data );
		$this->assertArrayHasKey( 'environment', $data );
		$this->assertArrayHasKey( 'plugins', $data );
		$this->assertArrayHasKey( 'themes', $data );
		$this->assertArrayHasKey( 'custom_fields', $data );
		$this->assertArrayHasKey( 'users', $data );
		$this->assertArrayHasKey( 'roles', $data );
	}

	/**
	 * Verify schema_version is 1.
	 *
	 * @return void
	 */
	public function test_schema_version_is_one(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$this->assertSame( 1, $data['schema_version'] );
	}

	/**
	 * Verify timestamp is ISO 8601 format.
	 *
	 * @return void
	 */
	public function test_timestamp_is_iso8601(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$parsed = DateTimeImmutable::createFromFormat( DateTimeInterface::ATOM, $data['timestamp'] );
		$this->assertInstanceOf( DateTimeImmutable::class, $parsed );
	}

	/**
	 * Verify site_url comes from WP function.
	 *
	 * @return void
	 */
	public function test_site_url(): void {
		$this->stub_wordpress_functions();
		Functions\expect( 'site_url' )->andReturn( 'https://example.tld' );

		$data = DataCollector::collect();

		$this->assertSame( 'https://example.tld', $data['site_url'] );
	}

	/**
	 * Verify environment contains required keys.
	 *
	 * @return void
	 */
	public function test_environment_keys(): void {
		$this->stub_wordpress_functions();

		$data        = DataCollector::collect();
		$environment = $data['environment'];

		$this->assertArrayHasKey( 'wp_version', $environment );
		$this->assertArrayHasKey( 'php_version', $environment );
		$this->assertArrayHasKey( 'mysql_version', $environment );
		$this->assertArrayHasKey( 'is_multisite', $environment );
		$this->assertArrayHasKey( 'active_theme', $environment );
		$this->assertArrayHasKey( 'active_theme_version', $environment );
		$this->assertArrayHasKey( 'mu_plugin_version', $environment );
	}

	/**
	 * Verify mu_plugin_version matches Plugin::VERSION.
	 *
	 * @return void
	 */
	public function test_mu_plugin_version(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$this->assertSame( Plugin::VERSION, $data['environment']['mu_plugin_version'] );
	}

	/**
	 * Verify plugins returns array.
	 *
	 * @return void
	 */
	public function test_plugins_is_array(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$this->assertIsArray( $data['plugins'] );
	}

	/**
	 * Verify themes returns array.
	 *
	 * @return void
	 */
	public function test_themes_is_array(): void {
		$this->stub_wordpress_functions();

		$data = DataCollector::collect();

		$this->assertIsArray( $data['themes'] );
	}

	/**
	 * Verify plugins include network_active field on multisite.
	 *
	 * @return void
	 */
	public function test_plugins_include_network_active(): void {
		$this->stub_base_functions( true );

		Functions\when( 'get_site_option' )->alias(
			static function ( string $option, mixed $fallback = false ): mixed {
				if ( $option === 'active_sitewide_plugins' ) {
					return [ 'akismet/akismet.php' => 1234567890 ];
				}

				return $fallback;
			},
		);

		$this->stub_two_plugins();

		$data    = DataCollector::collect();
		$plugins = $data['plugins'];

		$this->assertCount( 2, $plugins );

		$akismet = $this->find_plugin( $plugins, 'akismet' );
		$hello   = $this->find_plugin( $plugins, 'hello-dolly' );

		$this->assertNotNull( $akismet );
		$this->assertTrue( $akismet['network_active'] );

		$this->assertNotNull( $hello );
		$this->assertFalse( $hello['network_active'] );
	}

	/**
	 * Verify network_active is false on single-site.
	 *
	 * @return void
	 */
	public function test_plugins_network_active_false_on_single_site(): void {
		$this->stub_base_functions( false );
		Functions\when( 'get_site_option' )->justReturn( [] );

		Functions\when( 'get_plugins' )->justReturn(
			[
				'akismet/akismet.php' => [
					'Name'    => 'Akismet',
					'Version' => '5.0',
				],
			],
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = '' ): mixed {
				if ( $option === 'active_plugins' ) {
					return [ 'akismet/akismet.php' ];
				}

				return $fallback;
			},
		);

		$data    = DataCollector::collect();
		$plugins = $data['plugins'];

		$this->assertCount( 1, $plugins );
		$this->assertFalse( $plugins[0]['network_active'] );
	}

	/**
	 * Stub all WordPress functions needed for collect().
	 *
	 * @return void
	 */
	private function stub_wordpress_functions(): void {
		Functions\when( 'site_url' )->justReturn( 'https://example.tld' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'get_plugins' )->justReturn( [] );
		Functions\when( 'wp_get_themes' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);

		$this->stub_theme();

		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'add_action' )->justReturn( null );
		Functions\when( 'get_users' )->justReturn( [] );
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'get_site_option' )->justReturn( [] );

		$this->stub_roles();
	}

	/**
	 * Stub wp_get_theme to return a mock theme object.
	 *
	 * @return void
	 */
	private function stub_theme(): void {
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
	 * Stub wp_roles to return a mock WP_Roles object.
	 *
	 * @return void
	 */
	private function stub_roles(): void {
		$mock_roles = Mockery::mock( 'WP_Roles' );
		$mock_roles->roles = [];
		Functions\when( 'wp_roles' )->justReturn( $mock_roles );
	}

	/**
	 * Stub base WordPress functions without get_option/get_plugins/get_site_option.
	 *
	 * @param bool $multisite Whether to simulate multisite.
	 *
	 * @return void
	 */
	private function stub_base_functions( bool $multisite ): void {
		Functions\when( 'site_url' )->justReturn( 'https://example.tld' );
		Functions\when( 'is_multisite' )->justReturn( $multisite );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_get_themes' )->justReturn( [] );
		Functions\when( 'get_transient' )->justReturn( false );
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
		$this->stub_theme();
		$this->stub_roles();
	}

	/**
	 * Stub two plugins and an active_plugins option.
	 *
	 * @return void
	 */
	private function stub_two_plugins(): void {
		Functions\when( 'get_plugins' )->justReturn(
			[
				'akismet/akismet.php'   => [
					'Name'    => 'Akismet',
					'Version' => '5.0',
				],
				'hello-dolly/hello.php' => [
					'Name'    => 'Hello Dolly',
					'Version' => '1.7',
				],
			],
		);

		Functions\when( 'get_option' )->alias(
			static function ( string $option, mixed $fallback = '' ): mixed {
				if ( $option === 'active_plugins' ) {
					return [ 'hello-dolly/hello.php' ];
				}

				return $fallback;
			},
		);
	}

	/**
	 * Find a plugin by slug in the plugins array.
	 *
	 * @param array<int, array<string, mixed>> $plugins Plugin list.
	 * @param string                           $slug    Plugin slug.
	 *
	 * @return array<string, mixed>|null
	 */
	private function find_plugin( array $plugins, string $slug ): ?array {
		foreach ( $plugins as $plugin ) {
			if ( $plugin['slug'] === $slug ) {
				return $plugin;
			}
		}

		return null;
	}
}
