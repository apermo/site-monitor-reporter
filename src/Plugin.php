<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Main plugin class.
 */
class Plugin {

	public const VERSION = '0.1.0';

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private static string $file = '';

	/**
	 * Initialize the plugin.
	 *
	 * @param string $file Main plugin file path.
	 *
	 * @return void
	 */
	public static function init( string $file ): void {
		self::$file = $file;

		register_activation_hook( $file, [ self::class, 'activate' ] );
		register_deactivation_hook( $file, [ self::class, 'deactivate' ] );
		add_action( 'plugins_loaded', [ self::class, 'boot' ] );
	}

	/**
	 * Return the main plugin file path.
	 *
	 * @return string
	 */
	public static function file(): string {
		return self::$file;
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Cron::schedule();
	}

	/**
	 * Plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		Cron::unschedule();
		Cron::unschedule_network();
	}

	/**
	 * Boot the plugin after all plugins are loaded.
	 *
	 * @return void
	 */
	public static function boot(): void {
		Settings::register_hooks();
		Cron::register_hooks();
		CustomFields::register_hooks();
		AdminNotice::init();

		if ( MultisiteDetector::is_multisite() && MultisiteDetector::is_network_activated() ) {
			self::boot_network();
		} else {
			add_action( Cron::HOOK, [ Cron::class, 'on_version_check' ] );
		}
	}

	/**
	 * Register network-specific hooks on the main site.
	 *
	 * @return void
	 */
	private static function boot_network(): void {
		add_action( Cron::NETWORK_HOOK, [ Cron::class, 'on_network_push' ] );

		if ( MultisiteDetector::is_main_site() ) {
			Cron::schedule_network();
		}
	}
}
