<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

use WP_User;

/**
 * Collects user data for the monitoring report.
 *
 * By default fetches administrators only. The query args and
 * meta keys can be customized via filters.
 */
class UserCollector {

	/**
	 * Collect users matching the filterable query.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect_users(): array {
		$default_args = [
			'role'   => 'administrator',
			'fields' => 'all',
		];

		/**
		 * Filters the WP_User_Query args for the monitoring report.
		 *
		 * phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API hook.
		 *
		 * @param array<string, mixed> $args WP_User_Query arguments.
		 *
		 * @return array<string, mixed>
		 */
		$args = apply_filters( 'site_monitor_user_query_args', $default_args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		$users = get_users( $args );

		$meta_keys = self::get_meta_keys();

		$result = [];
		foreach ( $users as $user ) {
			$result[] = self::format_user( $user, $meta_keys );
		}

		return $result;
	}

	/**
	 * Get the filterable list of meta keys to include.
	 *
	 * @return array<string>
	 */
	private static function get_meta_keys(): array {
		$keys = [];

		// Built-in: Two-Factor plugin support.
		if ( is_plugin_active( 'two-factor/two-factor.php' ) ) {
			$keys[] = '_two_factor_enabled_providers';
		}

		/**
		 * Filters the user meta keys to include in the monitoring report.
		 *
		 * phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API hook.
		 *
		 * @param array<string> $keys Meta keys.
		 *
		 * @return array<string>
		 */
		return apply_filters( 'site_monitor_user_meta_keys', $keys ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Format a WP_User into the report structure.
	 *
	 * @param WP_User       $user      User object.
	 * @param array<string> $meta_keys Meta keys to collect.
	 *
	 * @return array<string, mixed>
	 */
	private static function format_user( WP_User $user, array $meta_keys ): array {
		$meta = [];

		foreach ( $meta_keys as $key ) {
			$value = get_user_meta( $user->ID, $key, true );

			if ( $key === '_two_factor_enabled_providers' ) {
				$meta['two_factor_enabled'] = \is_array( $value ) && $value !== [] ? 'true' : 'false';
				if ( \is_array( $value ) && $value !== [] ) {
					$meta['two_factor_provider'] = self::map_two_factor_provider( $value[0] ?? '' );
				}
				continue;
			}

			$meta[ $key ] = \is_string( $value ) ? $value : '';
		}

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
		return [
			'user_login'   => $user->user_login,
			'display_name' => $user->display_name,
			'email'        => $user->user_email,
			'roles'        => $user->roles,
			'meta'         => $meta,
		];
	}

	/**
	 * Map a Two-Factor provider class name to a short label.
	 *
	 * @param string $provider Provider class name.
	 *
	 * @return string
	 */
	private static function map_two_factor_provider( string $provider ): string {
		$map = [
			'Two_Factor_Totp'         => 'totp',
			'Two_Factor_FIDO_U2F'     => 'u2f',
			'Two_Factor_Email'        => 'email',
			'Two_Factor_Backup_Codes' => 'backup_codes',
		];

		return $map[ $provider ] ?? $provider;
	}
}
