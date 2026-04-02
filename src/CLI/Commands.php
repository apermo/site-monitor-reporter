<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter\CLI;

use Apermo\SiteBookkeeperReporter\DataCollector;
use Apermo\SiteBookkeeperReporter\ReportPusher;
use Apermo\SiteBookkeeperReporter\Settings;
use WP_CLI;

/**
 * WP-CLI commands for the Site Bookkeeper Reporter plugin.
 *
 * Provides subcommands to push reports, preview collected data,
 * and test the connection to the central monitoring hub.
 */
class Commands {

	/**
	 * Push a report to the monitoring hub immediately.
	 *
	 * Triggers the same logic as the cron handler, collecting
	 * all site data and sending it to the configured hub URL.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-bookkeeper report
	 *
	 * @return void
	 */
	public function report(): void {
		$hub_url = Settings::get_hub_url();
		$token   = Settings::get_token();

		if ( $hub_url === '' || $token === '' ) {
			WP_CLI::error( 'Hub URL and token must be configured.' );

			return;
		}

		WP_CLI::log( 'Pushing report to ' . $hub_url . '...' );

		$success = ReportPusher::push();

		if ( $success ) {
			WP_CLI::success( 'Report pushed successfully.' );

			return;
		}

		WP_CLI::error( 'Failed to push report.' );
	}

	/**
	 * Display the current collected data as a payload preview.
	 *
	 * Shows the data that would be sent to the hub without
	 * actually pushing. Useful for debugging and verification.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - summary
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-bookkeeper status
	 *     wp site-bookkeeper status --format=summary
	 *
	 * @param array<string>        $args       Positional arguments.
	 * @param array<string,string> $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		$data   = DataCollector::collect();
		$format = $assoc_args['format'] ?? 'json';

		if ( $format === 'summary' ) {
			$this->print_summary( $data );
			return;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- CLI output, not HTTP response.
		$json = \json_encode( $data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );

		if ( $json === false ) {
			WP_CLI::error( 'Failed to encode data as JSON.' );

			return;
		}

		WP_CLI::log( $json );
	}

	/**
	 * Test the connection to the monitoring hub.
	 *
	 * Sends a GET request with the configured bearer token to
	 * verify that the hub is reachable and the token is valid.
	 *
	 * ## EXAMPLES
	 *
	 *     wp site-bookkeeper test
	 *
	 * @return void
	 */
	public function test(): void {
		$hub_url = Settings::get_hub_url();
		$token   = Settings::get_token();

		if ( $hub_url === '' || $token === '' ) {
			WP_CLI::error( 'Hub URL and token must be configured.' );

			return;
		}

		WP_CLI::log( 'Testing connection to ' . $hub_url . '...' );

		$response = wp_remote_get(
			$hub_url,
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
				],
				'timeout' => 15,
			],
		);

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( 'Connection failed: ' . $response->get_error_message() );

			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			WP_CLI::success( 'Connection OK (' . $code . ') — ' . $hub_url );
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		WP_CLI::error( 'Hub returned HTTP ' . $code . ': ' . $body );
	}

	/**
	 * Print a human-readable summary of the collected data.
	 *
	 * @param array<string, mixed> $data Collected payload data.
	 *
	 * @return void
	 */
	private function print_summary( array $data ): void {
		$environment = $data['environment'] ?? [];

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Table row definitions.
		$items = [
			[
				'Field' => 'Site URL',
				'Value' => $data['site_url'] ?? '',
			],
			[
				'Field' => 'WordPress',
				'Value' => $environment['wp_version'] ?? '',
			],
			[
				'Field' => 'PHP',
				'Value' => $environment['php_version'] ?? '',
			],
			[
				'Field' => 'MySQL',
				'Value' => $environment['mysql_version'] ?? '',
			],
			[
				'Field' => 'Active Theme',
				'Value' => $environment['active_theme'] ?? '',
			],
			[
				'Field' => 'Multisite',
				'Value' => ( (bool) ( $environment['is_multisite'] ?? false ) ) ? 'yes' : 'no',
			],
			[
				'Field' => 'Plugins',
				'Value' => (string) \count( $data['plugins'] ?? [] ),
			],
			[
				'Field' => 'Themes',
				'Value' => (string) \count( $data['themes'] ?? [] ),
			],
			[
				'Field' => 'Users',
				'Value' => (string) \count( $data['users'] ?? [] ),
			],
			[
				'Field' => 'Roles',
				'Value' => (string) \count( $data['roles'] ?? [] ),
			],
			[
				'Field' => 'Custom Fields',
				'Value' => (string) \count( $data['custom_fields'] ?? [] ),
			],
		];

		WP_CLI\Utils\format_items( 'table', $items, [ 'Field', 'Value' ] );
	}
}
