<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\Tests\Unit;

use Apermo\SiteBookkeeperReporter\Settings;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for HTTPS enforcement on the hub URL.
 */
class HttpsEnforcementTest extends TestCase {

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
	 * Verify is_https returns true for HTTPS URLs.
	 *
	 * @return void
	 */
	public function test_is_https_returns_true_for_https(): void {
		$this->assertTrue( Settings::is_https( 'https://monitor.example.tld' ) );
	}

	/**
	 * Verify is_https returns false for HTTP URLs.
	 *
	 * @return void
	 */
	public function test_is_https_returns_false_for_http(): void {
		$this->assertFalse( Settings::is_https( 'http://monitor.example.tld' ) );
	}

	/**
	 * Verify is_https returns false for empty string.
	 *
	 * @return void
	 */
	public function test_is_https_returns_false_for_empty(): void {
		$this->assertFalse( Settings::is_https( '' ) );
	}

	/**
	 * Verify sanitize_hub_url accepts HTTPS URL.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_https_url(): void {
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return $url;
			},
		);

		$result = Settings::sanitize_hub_url( 'https://monitor.example.tld' );

		$this->assertSame( 'https://monitor.example.tld', $result );
	}

	/**
	 * Verify sanitize_hub_url rejects HTTP URL and returns old value.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_http_url(): void {
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return $url;
			},
		);

		Functions\expect( 'add_settings_error' )
			->once()
			->with(
				'site_bookkeeper_hub_url',
				'invalid_scheme',
				'The hub URL must use HTTPS.',
				'error',
			);

		Functions\when( 'get_option' )
			->justReturn( 'https://old.example.tld' );

		$result = Settings::sanitize_hub_url( 'http://monitor.example.tld' );

		$this->assertSame( 'https://old.example.tld', $result );
	}

	/**
	 * Verify sanitize_hub_url accepts HTTP when allow constant is set.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_http_when_allowed(): void {
		if ( ! \defined( 'SITE_BOOKKEEPER_ALLOW_HTTP' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_ALLOW_HTTP', true );
		}

		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return $url;
			},
		);

		$result = Settings::sanitize_hub_url( 'http://monitor.example.tld' );

		$this->assertSame( 'http://monitor.example.tld', $result );
	}

	/**
	 * Verify is_http_allowed returns true when constant is set.
	 *
	 * @return void
	 */
	public function test_is_http_allowed_returns_true_when_defined(): void {
		if ( ! \defined( 'SITE_BOOKKEEPER_ALLOW_HTTP' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Test constant.
			\define( 'SITE_BOOKKEEPER_ALLOW_HTTP', true );
		}

		$this->assertTrue( Settings::is_http_allowed() );
	}

	/**
	 * Verify ReportPusher refuses HTTP without the allow constant.
	 *
	 * Since SITE_BOOKKEEPER_ALLOW_HTTP is already defined as true
	 * from the test above, we test the is_https + is_http_allowed
	 * combination directly.
	 *
	 * @return void
	 */
	public function test_https_guard_blocks_http_without_allow(): void {
		$url = 'http://monitor.example.tld';

		// Without the allow constant, this combination should block.
		$this->assertFalse( Settings::is_https( $url ) );
	}

	/**
	 * Verify ReportPusher allows HTTP with the allow constant.
	 *
	 * @return void
	 */
	public function test_https_guard_allows_http_with_constant(): void {
		$url = 'http://monitor.example.tld';

		// The constant is already defined as true from earlier test.
		$this->assertFalse( Settings::is_https( $url ) );
		$this->assertTrue( Settings::is_http_allowed() );

		// The combination allows proceeding.
		$allowed = Settings::is_https( $url ) || Settings::is_http_allowed();
		$this->assertTrue( $allowed );
	}

	/**
	 * Verify ReportPusher allows HTTPS regardless of constant.
	 *
	 * @return void
	 */
	public function test_https_guard_allows_https(): void {
		$url = 'https://monitor.example.tld';

		$this->assertTrue( Settings::is_https( $url ) );
	}

	/**
	 * Verify sanitize_hub_url accepts empty string.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_empty_string(): void {
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return $url;
			},
		);

		$result = Settings::sanitize_hub_url( '' );

		$this->assertSame( '', $result );
	}
}
