<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

use WP_User;

/**
 * Shows admin notices for overdue updates and security vulnerabilities.
 *
 * Visibility controlled by SITE_BOOKKEEPER_NOTICE_RECIPIENTS constant
 * and the site_bookkeeper_show_notice filter.
 */
class AdminNotice {

	/**
	 * Transient key for hub status data.
	 *
	 * @var string
	 */
	public const HUB_STATUS_TRANSIENT = 'site_bookkeeper_hub_status';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_notices', [ self::class, 'render' ] );
	}

	/**
	 * Render admin notices if applicable.
	 *
	 * @return void
	 */
	public static function render(): void {
		$user = wp_get_current_user();
		if ( $user->ID === 0 ) {
			return;
		}

		$hub_status = get_transient( self::HUB_STATUS_TRANSIENT );
		if ( ! \is_array( $hub_status ) ) {
			return;
		}

		self::render_vulnerability_notice( $user, $hub_status );
		self::render_overdue_notice( $user, $hub_status );
	}

	/**
	 * Show vulnerability notice if applicable.
	 *
	 * @param WP_User              $user       Current user.
	 * @param array<string, mixed> $hub_status Hub status data.
	 *
	 * @return void
	 */
	private static function render_vulnerability_notice( WP_User $user, array $hub_status ): void {
		$vulnerabilities = $hub_status['vulnerabilities'] ?? [];
		if ( ! \is_array( $vulnerabilities ) || $vulnerabilities === [] ) {
			return;
		}

		if ( ! self::should_show( $user, 'error' ) ) {
			return;
		}

		$count = \count( $vulnerabilities );
		\printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Site Bookkeeper:', 'site-bookkeeper-reporter' ),
			esc_html(
				\sprintf(
					/* translators: %d: number of vulnerabilities */
					_n(
						'%d installed plugin has a known security vulnerability.',
						'%d installed plugins have known security vulnerabilities.',
						$count,
						'site-bookkeeper-reporter',
					),
					$count,
				),
			),
			esc_url( admin_url( 'update-core.php' ) ),
			esc_html__( 'Review updates &rarr;', 'site-bookkeeper-reporter' ),
		);
	}

	/**
	 * Show overdue notice if applicable.
	 *
	 * @param WP_User              $user       Current user.
	 * @param array<string, mixed> $hub_status Hub status data.
	 *
	 * @return void
	 */
	private static function render_overdue_notice( WP_User $user, array $hub_status ): void {
		$category = $hub_status['category'] ?? null;
		if ( ! \is_array( $category ) || ! isset( $category['overdue_hours'] ) ) {
			return;
		}

		if ( ! self::should_show( $user, 'warning' ) ) {
			return;
		}

		$overdue_hours = (int) $category['overdue_hours'];
		$overdue_count = self::count_overdue_updates( $overdue_hours );

		if ( $overdue_count === 0 ) {
			return;
		}

		$category_name = (string) ( $category['name'] ?? '' );

		\printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s',
			esc_html__( 'Site Bookkeeper:', 'site-bookkeeper-reporter' ),
			esc_html(
				\sprintf(
					/* translators: 1: overdue count, 2: category name, 3: threshold hours */
					_n(
						'%1$d pending update is overdue. (%2$s, threshold: %3$dh)', // phpcs:ignore Apermo.WordPress.NoHardcodedTableNames -- False positive.
						'%1$d pending updates are overdue. (%2$s, threshold: %3$dh)',
						$overdue_count,
						'site-bookkeeper-reporter',
					),
					$overdue_count,
					$category_name,
					$overdue_hours,
				),
			),
		);

		\printf(
			' <a href="%s">%s</a></p></div>',
			esc_url( admin_url( 'update-core.php' ) ),
			esc_html__( 'Update now &rarr;', 'site-bookkeeper-reporter' ), // phpcs:ignore Apermo.WordPress.NoHardcodedTableNames -- False positive.
		);
	}

	/**
	 * Count how many plugin/theme updates are overdue.
	 *
	 * @param int $overdue_hours Threshold in hours.
	 *
	 * @return int
	 */
	private static function count_overdue_updates( int $overdue_hours ): int {
		$threshold = \time() - ( $overdue_hours * 3600 );
		$cache = get_option( 'site_bookkeeper_update_release_dates', [] );
		if ( ! \is_array( $cache ) ) {
			return 0;
		}

		return self::count_overdue_plugins( $cache, $threshold )
			+ self::count_overdue_themes( $cache, $threshold );
	}

	/**
	 * Count overdue plugin updates.
	 *
	 * @param array<string, array{since: string}> $cache     Release date cache.
	 * @param int                                 $threshold Unix timestamp threshold.
	 *
	 * @return int
	 */
	private static function count_overdue_plugins( array $cache, int $threshold ): int {
		$plugin_updates = get_transient( 'update_plugins' );
		if ( ! \is_object( $plugin_updates ) || ! isset( $plugin_updates->response ) ) {
			return 0;
		}

		$count = 0;
		$all_plugins = get_plugins();

		foreach ( $plugin_updates->response as $file => $update ) {
			$slug = \dirname( $file );
			$slug = $slug === '.' ? \basename( $file, '.php' ) : $slug;
			$version = (string) ( $all_plugins[ $file ]['Version'] ?? '' );

			if ( self::is_entry_overdue( $cache, $slug . ':' . $version, $threshold ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Count overdue theme updates.
	 *
	 * @param array<string, array{since: string}> $cache     Release date cache.
	 * @param int                                 $threshold Unix timestamp threshold.
	 *
	 * @return int
	 */
	private static function count_overdue_themes( array $cache, int $threshold ): int {
		$theme_updates = get_transient( 'update_themes' );
		if ( ! \is_object( $theme_updates ) || ! isset( $theme_updates->response ) ) {
			return 0;
		}

		$count = 0;

		foreach ( \array_keys( $theme_updates->response ) as $stylesheet ) {
			$version = wp_get_theme( $stylesheet )->get( 'Version' );

			if ( self::is_entry_overdue( $cache, $stylesheet . ':' . $version, $threshold ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Check if a single cache entry is overdue.
	 *
	 * @param array<string, array{since: string}> $cache     Release date cache.
	 * @param string                              $cache_key Cache key.
	 * @param int                                 $threshold Unix timestamp threshold.
	 *
	 * @return bool
	 */
	private static function is_entry_overdue( array $cache, string $cache_key, int $threshold ): bool {
		if ( ! isset( $cache[ $cache_key ]['since'] ) ) {
			return false;
		}

		$since = \strtotime( $cache[ $cache_key ]['since'] );

		return $since !== false && $since < $threshold;
	}

	/**
	 * Check whether the current user should see a notice.
	 *
	 * @param WP_User $user     Current user.
	 * @param string  $severity Notice severity ('warning' or 'error').
	 *
	 * @return bool
	 */
	private static function should_show( WP_User $user, string $severity ): bool {
		$show = self::check_recipients_constant( $user );

		/**
		 * Filter whether to show a Site Bookkeeper admin notice.
		 *
		 * @param bool    $show     Result of the constant-based check.
		 * @param WP_User $user     Current WordPress user.
		 * @param string  $severity Notice severity: 'warning' (overdue) or 'error' (vulnerability).
		 *
		 * @return bool
		 */
		return apply_filters( 'site_bookkeeper_show_notice', $show, $user, $severity ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API hook.
	}

	/**
	 * Check user against the SITE_BOOKKEEPER_NOTICE_RECIPIENTS constant.
	 *
	 * @param WP_User $user Current user.
	 *
	 * @return bool
	 */
	private static function check_recipients_constant( WP_User $user ): bool {
		$config = \defined( 'SITE_BOOKKEEPER_NOTICE_RECIPIENTS' )
			? \json_decode( (string) \constant( 'SITE_BOOKKEEPER_NOTICE_RECIPIENTS' ), true )
			: null;

		if ( ! \is_array( $config ) ) {
			return \in_array( 'administrator', $user->roles, true );
		}

		if ( $config === [] ) {
			return false;
		}

		return self::matches_roles( $user, $config['roles'] ?? [] )
			|| self::matches_ids( $user, $config['ids'] ?? [] )
			|| self::matches_emails( $user, $config['emails'] ?? [] );
	}

	/**
	 * Check if user has one of the configured roles.
	 *
	 * @param WP_User $user  Current user.
	 * @param mixed   $roles Configured roles.
	 *
	 * @return bool
	 */
	private static function matches_roles( WP_User $user, mixed $roles ): bool {
		if ( ! \is_array( $roles ) ) {
			return false;
		}

		return \array_intersect( $roles, $user->roles ) !== [];
	}

	/**
	 * Check if user ID is in the configured list.
	 *
	 * @param WP_User $user Current user.
	 * @param mixed   $ids  Configured IDs.
	 *
	 * @return bool
	 */
	private static function matches_ids( WP_User $user, mixed $ids ): bool {
		return \is_array( $ids ) && \in_array( $user->ID, $ids, true );
	}

	/**
	 * Check if user email matches any configured pattern.
	 *
	 * @param WP_User $user   Current user.
	 * @param mixed   $emails Configured email patterns.
	 *
	 * @return bool
	 */
	private static function matches_emails( WP_User $user, mixed $emails ): bool {
		if ( ! \is_array( $emails ) ) {
			return false;
		}

		foreach ( $emails as $pattern ) {
			if ( \preg_match( '{^' . $pattern . '$}', $user->user_email ) ) {
				return true;
			}
		}

		return false;
	}
}
