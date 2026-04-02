<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Detects multisite environment and network activation state.
 *
 * Static utility class wrapping WordPress multisite functions
 * so they can be easily mocked in unit tests via Brain Monkey.
 */
class MultisiteDetector {

	/**
	 * Check if WordPress is running as multisite.
	 *
	 * @return bool
	 */
	public static function is_multisite(): bool {
		return is_multisite();
	}

	/**
	 * Check if the plugin is network-activated.
	 *
	 * @return bool
	 */
	public static function is_network_activated(): bool {
		if ( ! \function_exists( 'is_plugin_active_for_network' ) ) {
			return false;
		}

		return is_plugin_active_for_network(
			'site-bookkeeper-reporter/plugin.php',
		);
	}

	/**
	 * Check if the current site is the main site.
	 *
	 * @return bool
	 */
	public static function is_main_site(): bool {
		return is_main_site();
	}

	/**
	 * Get the main site URL.
	 *
	 * Returns network_site_url() on multisite, site_url() otherwise.
	 *
	 * @return string
	 */
	public static function get_main_site_url(): string {
		if ( is_multisite() ) {
			return network_site_url();
		}

		return site_url();
	}
}
