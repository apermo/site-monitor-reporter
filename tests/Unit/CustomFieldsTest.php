<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\CustomFields;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the CustomFields class.
 */
class CustomFieldsTest extends TestCase {

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
	 * Verify collect applies the filter.
	 *
	 * @return void
	 */
	public function test_collect_applies_filter(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'site_bookkeeper_custom_fields', [] )
			->andReturn( [] );

		CustomFields::collect();
	}

	/**
	 * Verify valid custom fields pass through.
	 *
	 * @return void
	 */
	public function test_valid_fields_pass_through(): void {
		$fields = [
			[
				'key'   => 'object_cache',
				'label' => 'Object Cache',
				'value' => 'Redis (connected)',
			],
		];

		Functions\when( 'apply_filters' )->justReturn( $fields );

		$result = CustomFields::collect();

		$this->assertCount( 1, $result );
		$this->assertSame( 'object_cache', $result[0]['key'] );
	}

	/**
	 * Verify malformed entries are stripped.
	 *
	 * @return void
	 */
	public function test_malformed_entries_are_stripped(): void {
		$fields = [
			[
				'key'   => 'valid',
				'label' => 'Valid Field',
				'value' => 'yes',
			],
			[
				'key' => 'missing_label_and_value',
			],
			'not_an_array',
			[
				'label' => 'Missing Key',
				'value' => 'test',
			],
		];

		Functions\when( 'apply_filters' )->justReturn( $fields );

		$result = CustomFields::collect();

		$this->assertCount( 1, $result );
		$this->assertSame( 'valid', $result[0]['key'] );
	}

	/**
	 * Verify optional status field is preserved.
	 *
	 * @return void
	 */
	public function test_status_field_is_preserved(): void {
		$fields = [
			[
				'key'    => 'object_cache',
				'label'  => 'Object Cache',
				'value'  => 'Redis (connected)',
				'status' => 'good',
			],
		];

		Functions\when( 'apply_filters' )->justReturn( $fields );

		$result = CustomFields::collect();

		$this->assertSame( 'good', $result[0]['status'] );
	}

	/**
	 * Verify collect_defaults returns comment status field.
	 *
	 * @return void
	 */
	public function test_collect_defaults_includes_comment_status(): void {
		$this->stub_defaults_dependencies();

		$fields = CustomFields::collect_defaults( [] );

		$keys = \array_column( $fields, 'key' );
		$this->assertContains( 'comment_status', $keys );
	}

	/**
	 * Verify collect_defaults returns permalink structure.
	 *
	 * @return void
	 */
	public function test_collect_defaults_includes_permalink(): void {
		$this->stub_defaults_dependencies();

		$fields = CustomFields::collect_defaults( [] );

		$keys = \array_column( $fields, 'key' );
		$this->assertContains( 'permalink_structure', $keys );
	}

	/**
	 * Verify collect_defaults returns robots status.
	 *
	 * @return void
	 */
	public function test_collect_defaults_includes_robots(): void {
		$this->stub_defaults_dependencies();

		$fields = CustomFields::collect_defaults( [] );

		$keys = \array_column( $fields, 'key' );
		$this->assertContains( 'blog_public', $keys );
	}

	/**
	 * Verify collect_defaults returns object cache info.
	 *
	 * @return void
	 */
	public function test_collect_defaults_includes_object_cache(): void {
		$this->stub_defaults_dependencies();

		$fields = CustomFields::collect_defaults( [] );

		$keys = \array_column( $fields, 'key' );
		$this->assertContains( 'object_cache', $keys );
	}

	/**
	 * Stub WP functions needed for collect_defaults().
	 *
	 * @return void
	 */
	/**
	 * Stub WP functions needed for collect_defaults().
	 *
	 * @return void
	 */
	private function stub_defaults_dependencies(): void {
		$options = [
			'default_comment_status' => 'open',
			'comment_moderation'     => '1',
			'permalink_structure'    => '/%postname%/',
			'blog_public'            => '1',
			'active_plugins'         => [],
		];

		Functions\when( 'get_option' )->alias(
			static fn( string $option, mixed $fallback = false ): mixed => $options[ $option ] ?? $fallback,
		);

		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'is_plugin_active' )->justReturn( false );
		Functions\when( 'wp_get_environment_type' )->justReturn( 'production' );
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.tld/wp-admin/' . $path,
		);
	}
}
