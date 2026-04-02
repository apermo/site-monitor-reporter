<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\RoleCollector;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the RoleCollector class.
 */
class RoleCollectorTest extends TestCase {

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
	 * Verify collect returns array.
	 *
	 * @return void
	 */
	public function test_collect_returns_array(): void {
		$this->stub_roles();

		$result = RoleCollector::collect();

		$this->assertIsArray( $result );
	}

	/**
	 * Verify role data includes required keys.
	 *
	 * @return void
	 */
	public function test_role_data_has_required_keys(): void {
		$this->stub_roles();

		$result = RoleCollector::collect();

		$this->assertNotEmpty( $result );
		$role = $result[0];

		$this->assertArrayHasKey( 'slug', $role );
		$this->assertArrayHasKey( 'name', $role );
		$this->assertArrayHasKey( 'is_custom', $role );
		$this->assertArrayHasKey( 'is_modified', $role );
		$this->assertArrayHasKey( 'capabilities', $role );
	}

	/**
	 * Verify core roles are not flagged as custom.
	 *
	 * @return void
	 */
	public function test_core_roles_are_not_custom(): void {
		$this->stub_roles();

		$result = RoleCollector::collect();
		$admin  = \array_values(
			\array_filter(
				$result,
				static fn( array $role ): bool => $role['slug'] === 'administrator',
			),
		);

		$this->assertNotEmpty( $admin );
		$this->assertFalse( $admin[0]['is_custom'] );
	}

	/**
	 * Verify custom roles are flagged.
	 *
	 * @return void
	 */
	public function test_custom_roles_are_flagged(): void {
		$this->stub_roles();

		$result     = RoleCollector::collect();
		$shop_admin = \array_values(
			\array_filter(
				$result,
				static fn( array $role ): bool => $role['slug'] === 'shop_manager',
			),
		);

		$this->assertNotEmpty( $shop_admin );
		$this->assertTrue( $shop_admin[0]['is_custom'] );
	}

	/**
	 * Stub wp_roles() with mock data.
	 *
	 * @return void
	 */
	private function stub_roles(): void {
		$admin_caps = [
			'edit_posts'     => true,
			'manage_options' => true,
		];
		$editor_caps = [ 'edit_posts' => true ];
		$shop_manager_caps = [
			'edit_posts' => true,
			'manage_woocommerce' => true,
		];

		$roles = Mockery::mock( 'WP_Roles' );
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooDeep -- Mirrors WP_Roles structure.
		$roles->roles = [
			'administrator' => [
				'name' => 'Administrator',
				'capabilities' => $admin_caps,
			],
			'editor'        => [
				'name' => 'Editor',
				'capabilities' => $editor_caps,
			],
			'shop_manager'  => [
				'name' => 'Shop Manager',
				'capabilities' => $shop_manager_caps,
			],
		];

		Functions\when( 'wp_roles' )->justReturn( $roles );
	}
}
