<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Pushes the collected report data to the central monitoring hub.
 *
 * Stores the enriched hub response (category, vulnerabilities)
 * in a transient for use by AdminNotice.
 */
class ReportPusher {

	/**
	 * Collect data and push to the hub.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function push(): bool {
		$hub_url = Settings::get_hub_url();
		$token   = Settings::get_token();

		if ( $hub_url === '' || $token === '' ) {
			return false;
		}

		if ( ! Settings::is_https( $hub_url ) && ! Settings::is_http_allowed() ) {
			return false;
		}

		$data     = DataCollector::collect();
		$response = wp_remote_post(
			\rtrim( $hub_url, '/' ) . '/report',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => (string) wp_json_encode( $data ),
				'timeout' => 30,
			],
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			self::store_hub_response( $response );

			return true;
		}

		return false;
	}

	/**
	 * Parse and store the enriched hub response.
	 *
	 * @param array<string, mixed> $response wp_remote_post response.
	 *
	 * @return void
	 */
	private static function store_hub_response( array $response ): void {
		$body = wp_remote_retrieve_body( $response );
		$data = \json_decode( $body, true );

		if ( ! \is_array( $data ) ) {
			return;
		}

		$hub_status = [];

		if ( isset( $data['category'] ) && \is_array( $data['category'] ) ) {
			$hub_status['category'] = $data['category'];
		}

		if ( isset( $data['vulnerabilities'] ) && \is_array( $data['vulnerabilities'] ) ) {
			$hub_status['vulnerabilities'] = $data['vulnerabilities'];
		}

		if ( $hub_status !== [] ) {
			set_transient( AdminNotice::HUB_STATUS_TRANSIENT, $hub_status, \DAY_IN_SECONDS );
		}
	}
}
