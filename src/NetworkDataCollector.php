<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Collects network-level data for multisite environments.
 *
 * Only meaningful on the main site of a WordPress multisite.
 * Gathers subsites, network-activated plugins, super admins,
 * and network settings for the monitoring report.
 */
class NetworkDataCollector {

	/**
	 * Collect all network data for the report payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
		return [
			'schema_version'   => 1,
			'timestamp'        => ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( DateTimeInterface::ATOM ),
			'main_site_url'    => network_site_url(),
			'subsites'         => self::collect_subsites(),
			'network_plugins'  => self::collect_network_plugins(),
			'super_admins'     => self::collect_super_admins(),
			'network_settings' => self::collect_network_settings(),
		];
	}

	/**
	 * Collect all subsites in the network.
	 *
	 * @return array<int, array{blog_id: int, url: string, label: string}>
	 */
	private static function collect_subsites(): array {
		$sites  = get_sites( [ 'number' => 0 ] );
		$result = [];

		foreach ( $sites as $site ) {
			$blog_id = (int) $site->blog_id;
			$details = get_blog_details( $site->blog_id );
			$label   = \is_object( $details ) ? $details->blogname : '';

			$result[] = [
				'blog_id' => $blog_id,
				'url'     => get_site_url( $blog_id ),
				'label'   => $label,
			];
		}

		return $result;
	}

	/**
	 * Collect network-activated plugins with metadata.
	 *
	 * @return array<int, array{slug: string, name: string, version: string}>
	 */
	private static function collect_network_plugins(): array {
		$active      = (array) get_site_option( 'active_sitewide_plugins', [] );
		$all_plugins = get_plugins();
		$result      = [];

		foreach ( \array_keys( $active ) as $file ) {
			if ( ! isset( $all_plugins[ $file ] ) ) {
				continue;
			}

			$slug     = \dirname( $file );
			$result[] = [
				'slug'    => $slug === '.' ? \basename( $file, '.php' ) : $slug,
				'name'    => $all_plugins[ $file ]['Name'] ?? '',
				'version' => $all_plugins[ $file ]['Version'] ?? '',
			];
		}

		return $result;
	}

	/**
	 * Collect super admin users with details.
	 *
	 * @return array<int, array{user_login: string, email: string, display_name: string}>
	 */
	private static function collect_super_admins(): array {
		$logins = get_super_admins();
		$result = [];

		foreach ( $logins as $login ) {
			$user = get_user_by( 'login', $login );

			if ( $user === false ) {
				continue;
			}

			$result[] = [
				'user_login'   => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
			];
		}

		return $result;
	}

	/**
	 * Collect key network settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_network_settings(): array {
		$settings = [
			'registration'     => (string) get_site_option( 'registration', '' ),
			'add_new_users'    => (string) get_site_option( 'add_new_users', '' ),
			'upload_filetypes' => (string) get_site_option( 'upload_filetypes', '' ),
		];

		/**
		 * Filters the network settings included in the report.
		 *
		 * phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API hook.
		 *
		 * @param array<string, mixed> $settings Network settings.
		 *
		 * @return array<string, mixed>
		 */
		return apply_filters( 'site_bookkeeper_network_settings', $settings ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
}
