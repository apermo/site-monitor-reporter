<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Manages the cron schedule for pushing reports.
 *
 * Hooks into wp_version_check to trigger a push alongside
 * WordPress core update checks, and schedules a twice-daily
 * cron event as fallback.
 *
 * On multisite with network activation, a separate network
 * cron handles all subsites from the main site.
 */
class Cron {

	public const HOOK         = 'site_bookkeeper_reporter_push';
	public const NETWORK_HOOK = 'site_bookkeeper_reporter_network_push';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'wp_version_check', [ self::class, 'on_version_check' ] );
	}

	/**
	 * Triggered on wp_version_check to push fresh data.
	 *
	 * When network-activated, per-site pushes are handled by the
	 * network cron on the main site, so this becomes a no-op.
	 *
	 * @return void
	 */
	public static function on_version_check(): void {
		if ( MultisiteDetector::is_multisite() && MultisiteDetector::is_network_activated() ) {
			return;
		}

		ReportPusher::push();
	}

	/**
	 * Handle network-wide report push from main site cron.
	 *
	 * Pushes the network report, then iterates all subsites
	 * and pushes each site's individual report.
	 *
	 * @return void
	 */
	public static function on_network_push(): void {
		if ( ! MultisiteDetector::is_network_activated() || ! MultisiteDetector::is_main_site() ) {
			return;
		}

		NetworkReportPusher::push();

		$sites = get_sites(
			[
				'number' => 0,
				'fields' => 'ids',
			],
		);

		foreach ( $sites as $blog_id ) {
			switch_to_blog( $blog_id );
			ReportPusher::push();
			restore_current_blog();
		}
	}

	/**
	 * Schedule the twice-daily cron event.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		wp_schedule_event( \time(), 'twicedaily', self::HOOK );
	}

	/**
	 * Schedule the network cron event on the main site.
	 *
	 * @return void
	 */
	public static function schedule_network(): void {
		if ( wp_next_scheduled( self::NETWORK_HOOK ) ) {
			return;
		}

		wp_schedule_event( \time(), 'twicedaily', self::NETWORK_HOOK );
	}

	/**
	 * Remove the scheduled cron event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Remove the network cron event.
	 *
	 * @return void
	 */
	public static function unschedule_network(): void {
		wp_clear_scheduled_hook( self::NETWORK_HOOK );
	}
}
