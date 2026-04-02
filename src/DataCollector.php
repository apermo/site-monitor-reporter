<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Collects site health data for the monitoring report.
 *
 * Reads WordPress core, plugin, and theme information from
 * transients, functions, and options to build the payload.
 */
class DataCollector {

	/**
	 * Collect all site data for the report payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function collect(): array {
		VersionTracker::reset();

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
		$data = [
			'schema_version' => 1,
			'timestamp'      => ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( DateTimeInterface::ATOM ),
			'site_url'       => site_url(),
			'environment'    => self::collect_environment(),
			'plugins'        => self::collect_plugins(),
			'themes'         => self::collect_themes(),
			'custom_fields'  => CustomFields::collect(),
			'users'          => UserCollector::collect_users(),
			'roles'          => RoleCollector::collect(),
		];

		VersionTracker::flush();

		return $data;
	}

	/**
	 * Collect environment information.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_environment(): array {
		global $wpdb;

		$theme         = wp_get_theme();
		$core_updates  = get_transient( 'update_core' );
		$theme_updates = get_transient( 'update_themes' );

		$wp_update_available = null;
		if ( \is_object( $core_updates ) && isset( $core_updates->updates ) && $core_updates->updates !== [] ) {
			foreach ( $core_updates->updates as $update ) {
				if ( $update->response === 'upgrade' ) {
					$wp_update_available = $update->current;
					break;
				}
			}
		}

		$active_theme_update = null;
		$stylesheet          = $theme->get_stylesheet();
		if ( \is_object( $theme_updates ) && isset( $theme_updates->response[ $stylesheet ] ) ) {
			$active_theme_update = $theme_updates->response[ $stylesheet ]['new_version'] ?? null;
		}

		$mysql_version = '';
		if ( isset( $wpdb ) && \is_object( $wpdb ) ) {
			$mysql_version = $wpdb->db_version();
		}

		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
		return [
			'wp_version'                    => $GLOBALS['wp_version'] ?? '',
			'wp_update_available'           => $wp_update_available,
			'wp_version_last_updated'       => VersionTracker::get_last_updated( 'core', 'wordpress', $GLOBALS['wp_version'] ?? '' ),
			'php_version'                   => \PHP_VERSION,
			'mysql_version'                 => $mysql_version,
			'is_multisite'                  => is_multisite(),
			'active_theme'                  => $theme->get_stylesheet(),
			'active_theme_version'          => $theme->get( 'Version' ),
			'active_theme_update_available' => $active_theme_update,
			'mu_plugin_version'             => Plugin::VERSION,
		];
	}

	/**
	 * Collect plugin information.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_plugins(): array {
		$all_plugins    = get_plugins();
		$plugin_updates = get_transient( 'update_plugins' );
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$result         = [];

		foreach ( $all_plugins as $file => $plugin_data ) {
			$slug             = \dirname( $file );
			$update_available = null;

			if ( \is_object( $plugin_updates ) && isset( $plugin_updates->response[ $file ] ) ) {
				$update_available = $plugin_updates->response[ $file ]->new_version ?? null;
			}

			// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
			$result[] = [
				'slug'             => $slug === '.' ? \basename( $file, '.php' ) : $slug,
				'name'             => $plugin_data['Name'] ?? '',
				'version'          => $plugin_data['Version'] ?? '',
				'update_available' => $update_available,
				'active'           => \in_array( $file, $active_plugins, true ),
				'last_updated'     => VersionTracker::get_last_updated( 'plugin', $slug === '.' ? \basename( $file, '.php' ) : $slug, $plugin_data['Version'] ?? '' ),
			];
		}

		return $result;
	}

	/**
	 * Collect theme information.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function collect_themes(): array {
		$all_themes    = wp_get_themes();
		$theme_updates = get_transient( 'update_themes' );
		$active_theme  = wp_get_theme();
		$result        = [];

		foreach ( $all_themes as $stylesheet => $theme ) {
			$update_available = null;

			if ( \is_object( $theme_updates ) && isset( $theme_updates->response[ $stylesheet ] ) ) {
				$update_available = $theme_updates->response[ $stylesheet ]['new_version'] ?? null;
			}

			// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
			$result[] = [
				'slug'             => $stylesheet,
				'name'             => $theme->get( 'Name' ),
				'version'          => $theme->get( 'Version' ),
				'update_available' => $update_available,
				'active'           => $stylesheet === $active_theme->get_stylesheet(),
				'last_updated'     => VersionTracker::get_last_updated( 'theme', $stylesheet, $theme->get( 'Version' ) ),
			];
		}

		return $result;
	}
}
