<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

/**
 * Settings page for configuring the monitoring hub connection.
 *
 * Settings can be defined via constants in wp-config.php:
 * - SITE_MONITOR_HUB_URL: The URL of the monitoring hub.
 * - SITE_MONITOR_TOKEN: The authentication token for the hub.
 *
 * When constants are defined, they take precedence over database options
 * and the corresponding fields are displayed as read-only.
 */
class Settings {

	/**
	 * Register hooks for the settings page.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		add_action( 'admin_init', [ self::class, 'register_settings' ] );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_options_page(
			'Site Monitor Reporter',
			'Site Monitor',
			'manage_options',
			'site-monitor-reporter',
			[ self::class, 'render_page' ],
		);
	}

	/**
	 * Register settings, section, and fields.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		register_setting(
			'site_monitor_reporter',
			'site_monitor_hub_url',
			[
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			],
		);

		register_setting(
			'site_monitor_reporter',
			'site_monitor_token',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
		);

		add_settings_section(
			'site_monitor_reporter_main',
			'Connection Settings',
			[ self::class, 'render_section' ],
			'site-monitor-reporter',
		);

		add_settings_field(
			'site_monitor_hub_url',
			'Hub URL',
			[ self::class, 'render_hub_url_field' ],
			'site-monitor-reporter',
			'site_monitor_reporter_main',
		);

		add_settings_field(
			'site_monitor_token',
			'Token',
			[ self::class, 'render_token_field' ],
			'site-monitor-reporter',
			'site_monitor_reporter_main',
		);
	}

	/**
	 * Get the hub URL, preferring the constant over the option.
	 *
	 * @return string
	 */
	public static function get_hub_url(): string {
		if ( self::is_hub_url_constant() ) {
			return \SITE_MONITOR_HUB_URL;
		}

		return (string) get_option( 'site_monitor_hub_url', '' );
	}

	/**
	 * Get the token, preferring the constant over the option.
	 *
	 * @return string
	 */
	public static function get_token(): string {
		if ( self::is_token_constant() ) {
			return \SITE_MONITOR_TOKEN;
		}

		return (string) get_option( 'site_monitor_token', '' );
	}

	/**
	 * Check if the hub URL is defined as a constant.
	 *
	 * @return bool
	 */
	public static function is_hub_url_constant(): bool {
		return \defined( 'SITE_MONITOR_HUB_URL' );
	}

	/**
	 * Check if the token is defined as a constant.
	 *
	 * @return bool
	 */
	public static function is_token_constant(): bool {
		return \defined( 'SITE_MONITOR_TOKEN' );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		?>
		<div class="wrap">
			<h1>Site Monitor Reporter</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'site_monitor_reporter' );
				do_settings_sections( 'site-monitor-reporter' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the section description.
	 *
	 * @return void
	 */
	public static function render_section(): void {
		echo '<p>Configure the connection to the central monitoring hub.</p>';
	}

	/**
	 * Render the hub URL field.
	 *
	 * @return void
	 */
	public static function render_hub_url_field(): void {
		$value    = self::get_hub_url();
		$disabled = self::is_hub_url_constant();

		\printf(
			'<input type="url" name="site_monitor_hub_url" value="%s" class="regular-text" %s />',
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">Defined via <code>SITE_MONITOR_HUB_URL</code> constant.</p>';
		}
	}

	/**
	 * Render the token field.
	 *
	 * @return void
	 */
	public static function render_token_field(): void {
		$value    = self::get_token();
		$disabled = self::is_token_constant();

		\printf(
			'<input type="password" name="site_monitor_token" value="%s" class="regular-text" %s />',
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">Defined via <code>SITE_MONITOR_TOKEN</code> constant.</p>';
		}
	}
}
