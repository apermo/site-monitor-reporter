<?php
/**
 * PHPStan stubs for the WP_CLI class.
 *
 * Allows static analysis without requiring wp-cli/wp-cli
 * as a Composer dependency.
 */

// phpcs:disable

class WP_CLI {

	/**
	 * @param string $message
	 * @return void
	 */
	public static function log( string $message ): void {}

	/**
	 * @param string $message
	 * @return void
	 */
	public static function success( string $message ): void {}

	/**
	 * @param string|WP_Error $message
	 * @param bool $exit
	 * @return void
	 */
	public static function error( $message, bool $exit = true ): void {}

	/**
	 * @param string $name
	 * @param string|callable|class-string $callable
	 * @param array<string, mixed> $args
	 * @return void
	 */
	public static function add_command( string $name, $callable, array $args = [] ): void {}
}
