<?php
/**
 * Minimal WP_CLI stub for unit testing.
 *
 * Captures output instead of writing to stdout or exiting.
 * Reset static properties in setUp() before each test.
 *
 * phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 * phpcs:disable Squiz.Classes.ClassFileName.NoMatch
 */

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound, Squiz.Commenting.ClassComment.Missing
class WP_CLI {

	/**
	 * Captured log messages.
	 *
	 * @var array<string>
	 */
	public static array $logs = [];

	/**
	 * Last success message.
	 *
	 * @var string
	 */
	public static string $success = '';

	/**
	 * Last error message.
	 *
	 * @var string
	 */
	public static string $error = '';

	/**
	 * Stub for WP_CLI::log().
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	public static function log( string $message ): void {
		self::$logs[] = $message;
	}

	/**
	 * Stub for WP_CLI::success().
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	public static function success( string $message ): void {
		self::$success = $message;
	}

	/**
	 * Stub for WP_CLI::error().
	 *
	 * Does NOT exit, unlike the real WP_CLI::error().
	 *
	 * @param string $message Message.
	 *
	 * @return void
	 */
	public static function error( string $message ): void {
		self::$error = $message;
	}

	/**
	 * Stub for WP_CLI::add_command().
	 *
	 * @param string $name    Command name.
	 * @param mixed  $handler Handler class or callable.
	 *
	 * @return void
	 */
	public static function add_command( string $name, mixed $handler ): void {
		// No-op in tests.
	}
}
