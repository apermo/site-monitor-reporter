<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\NetworkDataCollector;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NetworkDataCollector class.
 */
class NetworkDataCollectorTest extends TestCase {

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
	 * Verify collect returns required top-level keys.
	 *
	 * @return void
	 */
	public function test_collect_returns_required_keys(): void {
		$this->stub_network_functions();

		$data = NetworkDataCollector::collect();

		$this->assertArrayHasKey( 'schema_version', $data );
		$this->assertArrayHasKey( 'timestamp', $data );
		$this->assertArrayHasKey( 'main_site_url', $data );
		$this->assertArrayHasKey( 'subsites', $data );
		$this->assertArrayHasKey( 'network_plugins', $data );
		$this->assertArrayHasKey( 'super_admins', $data );
		$this->assertArrayHasKey( 'network_settings', $data );
	}

	/**
	 * Verify schema_version is 1.
	 *
	 * @return void
	 */
	public function test_schema_version_is_one(): void {
		$this->stub_network_functions();

		$data = NetworkDataCollector::collect();

		$this->assertSame( 1, $data['schema_version'] );
	}

	/**
	 * Verify main_site_url uses network_site_url.
	 *
	 * @return void
	 */
	public function test_main_site_url(): void {
		$this->stub_network_functions();

		$data = NetworkDataCollector::collect();

		$this->assertSame( 'https://network.example.tld/', $data['main_site_url'] );
	}

	/**
	 * Verify subsites are collected.
	 *
	 * @return void
	 */
	public function test_collect_subsites(): void {
		$site1          = Mockery::mock( 'WP_Site' );
		$site1->blog_id = '1';

		$site2          = Mockery::mock( 'WP_Site' );
		$site2->blog_id = '2';

		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );

		Functions\expect( 'get_sites' )
			->once()
			->with( [ 'number' => 0 ] )
			->andReturn( [ $site1, $site2 ] );

		Functions\when( 'get_blog_details' )->alias(
			// phpcs:ignore Squiz.Commenting.FunctionComment.Missing -- Test stub.
			static function ( string $blog_id ): object {
				$names = [
					'1' => 'Main Site',
					'2' => 'Blog Two',
				];

				// phpcs:ignore Apermo.PHP.ForbiddenObjectCast.Found -- Test stub for WP_Site.
				return (object) [ 'blogname' => $names[ $blog_id ] ?? '' ];
			},
		);

		Functions\when( 'get_site_url' )->alias(
			static function ( int $blog_id ): string {
				$urls = [
					1 => 'https://network.example.tld',
					2 => 'https://blog2.example.tld',
				];

				return $urls[ $blog_id ] ?? '';
			},
		);

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

		$data     = NetworkDataCollector::collect();
		$subsites = $data['subsites'];

		$this->assertCount( 2, $subsites );
		$this->assertSame( 1, $subsites[0]['blog_id'] );
		$this->assertSame( 'https://network.example.tld', $subsites[0]['url'] );
		$this->assertSame( 'Main Site', $subsites[0]['label'] );
		$this->assertSame( 2, $subsites[1]['blog_id'] );
		$this->assertSame( 'https://blog2.example.tld', $subsites[1]['url'] );
		$this->assertSame( 'Blog Two', $subsites[1]['label'] );
	}

	/**
	 * Verify network plugins are collected.
	 *
	 * @return void
	 */
	public function test_collect_network_plugins(): void {
		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );
		Functions\when( 'get_sites' )->justReturn( [] );
		Functions\when( 'get_super_admins' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);

		Functions\when( 'get_site_option' )->alias(
			static function ( string $option, mixed $fallback = false ): mixed {
				if ( $option === 'active_sitewide_plugins' ) {
					return [ 'akismet/akismet.php' => 1234567890 ];
				}

				return $fallback;
			},
		);

		Functions\expect( 'get_plugins' )
			->andReturn(
				[
					'akismet/akismet.php' => [
						'Name'    => 'Akismet',
						'Version' => '5.0',
					],
				],
			);

		$data    = NetworkDataCollector::collect();
		$plugins = $data['network_plugins'];

		$this->assertCount( 1, $plugins );
		$this->assertSame( 'akismet', $plugins[0]['slug'] );
		$this->assertSame( 'Akismet', $plugins[0]['name'] );
		$this->assertSame( '5.0', $plugins[0]['version'] );
	}

	/**
	 * Verify super admins are collected.
	 *
	 * @return void
	 */
	public function test_collect_super_admins(): void {
		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );
		Functions\when( 'get_sites' )->justReturn( [] );
		Functions\when( 'get_site_option' )->alias(
			static function ( string $option, mixed $fallback = false ): mixed {
				return $fallback;
			},
		);
		Functions\when( 'get_plugins' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);

		Functions\expect( 'get_super_admins' )
			->once()
			->andReturn( [ 'admin' ] );

		$user               = Mockery::mock( 'WP_User' );
		$user->user_login   = 'admin';
		$user->user_email   = 'admin@example.tld';
		$user->display_name = 'Admin User';

		Functions\expect( 'get_user_by' )
			->with( 'login', 'admin' )
			->andReturn( $user );

		$data   = NetworkDataCollector::collect();
		$admins = $data['super_admins'];

		$this->assertCount( 1, $admins );
		$this->assertSame( 'admin', $admins[0]['user_login'] );
		$this->assertSame( 'admin@example.tld', $admins[0]['email'] );
		$this->assertSame( 'Admin User', $admins[0]['display_name'] );
	}

	/**
	 * Verify network settings are collected.
	 *
	 * @return void
	 */
	public function test_collect_network_settings(): void {
		Functions\when( 'network_site_url' )
			->justReturn( 'https://network.example.tld/' );
		Functions\when( 'get_sites' )->justReturn( [] );
		Functions\when( 'get_plugins' )->justReturn( [] );
		Functions\when( 'get_super_admins' )->justReturn( [] );

		$options = [
			'registration'     => 'none',
			'add_new_users'    => '1',
			'upload_filetypes' => 'jpg jpeg png gif',
		];

		Functions\when( 'get_site_option' )->alias(
			static function ( string $option, mixed $fallback = false ) use ( $options ): mixed {
				return $options[ $option ] ?? $fallback;
			},
		);

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);

		$data     = NetworkDataCollector::collect();
		$settings = $data['network_settings'];

		$this->assertArrayHasKey( 'registration', $settings );
		$this->assertSame( 'none', $settings['registration'] );
		$this->assertSame( '1', $settings['add_new_users'] );
		$this->assertSame( 'jpg jpeg png gif', $settings['upload_filetypes'] );
	}

	/**
	 * Stub all WordPress network functions needed for collect().
	 *
	 * @return void
	 */
	private function stub_network_functions(): void {
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
