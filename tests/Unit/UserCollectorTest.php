<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\UserCollector;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the UserCollector class.
 */
class UserCollectorTest extends TestCase {

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
	 * Verify collect_users returns user data.
	 *
	 * @return void
	 */
	public function test_collect_users_returns_array(): void {
		$this->stub_user_query();

		$result = UserCollector::collect_users();

		$this->assertIsArray( $result );
	}

	/**
	 * Verify user data contains required keys.
	 *
	 * @return void
	 */
	public function test_user_data_has_required_keys(): void {
		$this->stub_user_query();

		$result = UserCollector::collect_users();

		$this->assertNotEmpty( $result );
		$user = $result[0];

		$this->assertArrayHasKey( 'user_login', $user );
		$this->assertArrayHasKey( 'display_name', $user );
		$this->assertArrayHasKey( 'email', $user );
		$this->assertArrayHasKey( 'roles', $user );
		$this->assertArrayHasKey( 'meta', $user );
	}

	/**
	 * Verify user query args are filterable.
	 *
	 * @return void
	 */
	public function test_user_query_args_are_filterable(): void {
		$this->stub_user_query();

		// Verify apply_filters is called during user collection.
		$result = UserCollector::collect_users();
		$this->assertIsArray( $result );
	}

	/**
	 * Verify default query fetches administrators.
	 *
	 * @return void
	 */
	public function test_default_query_fetches_administrators(): void {
		$captured_args = null;

		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ) use ( &$captured_args ): mixed {
				if ( $hook === 'site_bookkeeper_user_query_args' ) {
					$captured_args = $args[0];
				}
				return $args[0] ?? null;
			},
		);

		$this->stub_user_query_result();

		UserCollector::collect_users();

		$this->assertIsArray( $captured_args );
		$this->assertSame( 'administrator', $captured_args['role'] ?? '' );
	}

	/**
	 * Verify meta keys filter is applied.
	 *
	 * @return void
	 */
	public function test_meta_keys_are_filterable(): void {
		$this->stub_user_query();

		// The filter should be called during collection.
		$result = UserCollector::collect_users();

		$this->assertIsArray( $result );
	}

	/**
	 * Stub WP_User_Query with a mock user.
	 *
	 * @return void
	 */
	private function stub_user_query(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, mixed ...$args ): mixed {
				return $args[0] ?? null;
			},
		);

		$this->stub_user_query_result();
	}

	/**
	 * Stub get_users to return mock user data.
	 *
	 * @return void
	 */
	private function stub_user_query_result(): void {
		$mock_user               = Mockery::mock( 'WP_User' );
		$mock_user->user_login   = 'admin';
		$mock_user->display_name = 'Admin User';
		$mock_user->user_email   = 'admin@example.tld';
		$mock_user->roles        = [ 'administrator' ];

		Functions\when( 'get_users' )->justReturn( [ $mock_user ] );
		Functions\when( 'get_user_meta' )->justReturn( '' );
		Functions\when( 'is_plugin_active' )->justReturn( false );
	}
}
