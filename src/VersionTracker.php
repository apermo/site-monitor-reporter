<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Tracks version changes and persists last_updated timestamps.
 *
 * Stores previous versions in a wp_option and compares on each run.
 * If a version changed, last_updated is set to now. If unchanged,
 * the previous last_updated timestamp carries forward.
 */
class VersionTracker {

	private const OPTION_KEY = 'site_bookkeeper_version_tracking';

	/**
	 * In-memory cache of tracked versions for the current request.
	 *
	 * @var array<string, array{version: string, last_updated: string}>|null
	 */
	private static ?array $tracked = null;

	/**
	 * Get the last_updated timestamp for a versioned item.
	 *
	 * Compares the given version with the stored version. If changed
	 * or first seen, sets last_updated to now. Otherwise carries
	 * forward the existing timestamp.
	 *
	 * @param string $type Item type (core, plugin, theme).
	 * @param string $slug Item identifier.
	 * @param string $version Current version string.
	 *
	 * @return string ISO 8601 timestamp.
	 */
	public static function get_last_updated( string $type, string $slug, string $version ): string {
		self::load();

		$key       = $type . ':' . $slug;
		$timestamp = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( DateTimeInterface::ATOM );

		if ( ! isset( self::$tracked[ $key ] ) || self::$tracked[ $key ]['version'] !== $version ) {
			self::$tracked[ $key ] = [
				'version'      => $version,
				'last_updated' => $timestamp,
			];

			return $timestamp;
		}

		return self::$tracked[ $key ]['last_updated'];
	}

	/**
	 * Persist tracked versions to the database.
	 *
	 * @return void
	 */
	public static function flush(): void {
		if ( self::$tracked === null ) {
			return;
		}

		update_option( self::OPTION_KEY, self::$tracked, false );
	}

	/**
	 * Load tracked versions from the database if not yet loaded.
	 *
	 * @return void
	 */
	private static function load(): void {
		if ( self::$tracked !== null ) {
			return;
		}

		$stored = get_option( self::OPTION_KEY, [] );

		self::$tracked = \is_array( $stored ) ? $stored : [];
	}

	/**
	 * Reset the in-memory cache. Used for testing.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$tracked = null;
	}
}
