<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Pushes the collected report data to the central monitoring hub.
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

		return $code >= 200 && $code < 300;
	}
}
