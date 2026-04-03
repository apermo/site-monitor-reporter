<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Tracks when plugin updates first became available.
 *
 * Tier 1 (wordpress.org): fetches actual release date from Trac RSS feed.
 * Tier 2 (non-wordpress.org): stores date when reporter first saw the update.
 */
class UpdateReleaseTracker {

	/**
	 * WordPress option key for cached release dates.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'site_bookkeeper_update_release_dates';

	/**
	 * Trac RSS feed URL template.
	 *
	 * @var string
	 */
	private const TRAC_RSS_URL = 'https://plugins.trac.wordpress.org/log/%s/tags?format=rss&limit=50';

	/**
	 * Get the date when an update first became available for a plugin.
	 *
	 * @param string $slug             Plugin slug.
	 * @param string $installed_version Currently installed version.
	 * @param string $update_available  Available update version.
	 *
	 * @return array{since: string, source: string}|null
	 */
	public static function get_update_since( string $slug, string $installed_version, string $update_available ): ?array {
		if ( $update_available === '' ) {
			self::clear_slug( $slug );

			return null;
		}

		$cache = self::load_cache();
		$cache_key = $slug . ':' . $installed_version;

		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$result = self::is_wordpress_org_plugin( $slug )
			? self::fetch_release_date( $slug, $installed_version )
			: null;

		if ( $result === null ) {
			$result = [
				'since'  => \gmdate( 'c' ),
				'source' => 'first_seen',
			];
		}

		$cache[ $cache_key ] = $result;
		$cache = self::prune_stale_entries( $cache, $slug, $cache_key );
		self::save_cache( $cache );

		return $result;
	}

	/**
	 * Check if a plugin is hosted on wordpress.org.
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return bool
	 */
	private static function is_wordpress_org_plugin( string $slug ): bool {
		$updates = get_transient( 'update_plugins' );
		if ( ! \is_object( $updates ) ) {
			return false;
		}

		$all_plugins = \array_merge(
			(array) ( $updates->response ?? [] ),
			(array) ( $updates->no_update ?? [] ),
		);

		foreach ( $all_plugins as $plugin ) {
			if ( ! \is_object( $plugin ) ) {
				continue;
			}

			$plugin_slug = $plugin->slug ?? '';
			if ( $plugin_slug !== $slug ) {
				continue;
			}

			$package = $plugin->package ?? '';
			if ( \is_string( $package ) && \str_contains( $package, 'downloads.wordpress.org' ) ) {
				return true;
			}

			$url = $plugin->url ?? '';
			if ( \is_string( $url ) && \str_contains( $url, 'wordpress.org' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Fetch the release date for the first version after installed from Trac RSS.
	 *
	 * @param string $slug              Plugin slug.
	 * @param string $installed_version Currently installed version.
	 *
	 * @return array{since: string, source: string}|null
	 */
	private static function fetch_release_date( string $slug, string $installed_version ): ?array {
		$url = \sprintf( self::TRAC_RSS_URL, $slug );

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			return null;
		}

		return self::parse_rss_for_version( $body, $installed_version );
	}

	/**
	 * Parse Trac RSS feed to find the first version after installed.
	 *
	 * @param string $feed_body          RSS XML content.
	 * @param string $installed_version Currently installed version.
	 *
	 * @return array{since: string, source: string}|null
	 */
	private static function parse_rss_for_version( string $feed_body, string $installed_version ): ?array {
		$previous_error = \libxml_use_internal_errors( true );
		$feed = \simplexml_load_string( $feed_body );
		\libxml_use_internal_errors( $previous_error );

		if ( $feed === false ) {
			return null;
		}

		$releases = [];

		foreach ( $feed->channel->item ?? [] as $item ) {
			$title = (string) $item->title;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- XML element name.
			$date = (string) $item->pubDate;

			$version = self::extract_version_from_title( $title );
			if ( $version === null ) {
				continue;
			}

			if ( \version_compare( $version, $installed_version, '>' ) ) {
				$timestamp = \strtotime( $date );
				if ( $timestamp !== false ) {
					$releases[ $version ] = $timestamp;
				}
			}
		}

		if ( $releases === [] ) {
			return null;
		}

		// Find the earliest release that is newer than installed.
		\asort( $releases );
		$earliest_timestamp = \reset( $releases );

		return [
			'since'  => \gmdate( 'c', $earliest_timestamp ),
			'source' => 'release_date',
		];
	}

	/**
	 * Extract a version number from a Trac RSS item title.
	 *
	 * Titles typically look like "Created tag 1.2.3" or just "1.2.3".
	 * Filter out non-release items like "tested up to" bumps.
	 *
	 * @param string $title RSS item title.
	 *
	 * @return string|null Version string or null.
	 */
	private static function extract_version_from_title( string $title ): ?string {
		if ( \preg_match( '/(\d+\.\d+(?:\.\d+)*)/', $title, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Remove all cache entries for a slug (plugin is up to date).
	 *
	 * @param string $slug Plugin slug.
	 *
	 * @return void
	 */
	private static function clear_slug( string $slug ): void {
		$cache = self::load_cache();
		$prefix = $slug . ':';
		$changed = false;

		foreach ( \array_keys( $cache ) as $key ) {
			if ( \str_starts_with( $key, $prefix ) ) {
				unset( $cache[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			self::save_cache( $cache );
		}
	}

	/**
	 * Remove old version entries for a slug, keeping only the current one.
	 *
	 * @param array<string, array{since: string, source: string}> $cache    Cache data.
	 * @param string                                              $slug     Plugin slug.
	 * @param string                                              $keep_key Cache key to keep.
	 *
	 * @return array<string, array{since: string, source: string}>
	 */
	private static function prune_stale_entries( array $cache, string $slug, string $keep_key ): array {
		$prefix = $slug . ':';

		foreach ( \array_keys( $cache ) as $key ) {
			if ( \str_starts_with( $key, $prefix ) && $key !== $keep_key ) {
				unset( $cache[ $key ] );
			}
		}

		return $cache;
	}

	/**
	 * Load the cached release dates.
	 *
	 * @return array<string, array{since: string, source: string}>
	 */
	private static function load_cache(): array {
		$cache = get_option( self::OPTION_KEY, [] );

		return \is_array( $cache ) ? $cache : [];
	}

	/**
	 * Save the release date cache.
	 *
	 * @param array<string, array{since: string, source: string}> $cache Cache data.
	 *
	 * @return void
	 */
	private static function save_cache( array $cache ): void {
		update_option( self::OPTION_KEY, $cache, false );
	}
}
