<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

/**
 * Manages the cron schedule for pushing reports.
 *
 * Hooks into wp_version_check to trigger a push alongside
 * WordPress core update checks, and schedules a twice-daily
 * cron event as fallback.
 */
class Cron {

	public const HOOK = 'site_monitor_reporter_push';

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
	 * @return void
	 */
	public static function on_version_check(): void {
		ReportPusher::push();
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
	 * Remove the scheduled cron event.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
