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
		add_action( 'admin_post_site_monitor_install_mu', [ self::class, 'handle_install_mu' ] );
		add_action( 'admin_post_site_monitor_remove_mu', [ self::class, 'handle_remove_mu' ] );
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

			<h2>MU-Plugin Loader</h2>
			<?php self::render_mu_plugin_status(); ?>
		</div>
		<?php
	}

	/**
	 * Render the mu-plugin installer status and actions.
	 *
	 * @return void
	 */
	public static function render_mu_plugin_status(): void {
		$installed = MuPluginInstaller::is_installed();
		$admin_url = admin_url( 'admin-post.php' );

		if ( $installed ) {
			echo '<p><span class="dashicons dashicons-yes-alt" style="color:#46b450;"></span> ';
			echo 'MU-Plugin loader is installed.</p>';

			\printf(
				'<form method="post" action="%s">',
				esc_url( $admin_url ),
			);
			echo '<input type="hidden" name="action" value="site_monitor_remove_mu" />';
			wp_nonce_field( 'site_monitor_remove_mu' );
			submit_button( 'Remove MU-Plugin Loader', 'delete', 'submit', false );
			echo '</form>';
		} else {
			echo '<p><span class="dashicons dashicons-warning" style="color:#dba617;"></span> ';
			echo 'MU-Plugin loader is not installed.</p>';

			\printf(
				'<form method="post" action="%s">',
				esc_url( $admin_url ),
			);
			echo '<input type="hidden" name="action" value="site_monitor_install_mu" />';
			wp_nonce_field( 'site_monitor_install_mu' );
			submit_button( 'Install MU-Plugin Loader', 'primary', 'submit', false );
			echo '</form>';
		}
	}

	/**
	 * Handle mu-plugin installation request.
	 *
	 * @return void
	 */
	public static function handle_install_mu(): void {
		check_admin_referer( 'site_monitor_install_mu' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'site-monitor-reporter' ) );
		}

		$success = MuPluginInstaller::install();

		if ( ! $success ) {
			$content = MuPluginInstaller::get_loader_content();
			$path    = MuPluginInstaller::get_loader_path();

			wp_die(
				\sprintf(
					'<h1>Could not write MU-Plugin loader</h1>'
					. '<p>Please create the file manually at <code>%s</code> with this content:</p>'
					. '<pre>%s</pre>'
					. '<p><a href="%s">Back to settings</a></p>',
					esc_html( $path ),
					esc_html( $content ),
					esc_url( admin_url( 'options-general.php?page=site-monitor-reporter' ) ),
				),
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=site-monitor-reporter&mu-installed=1' ) );
		exit();
	}

	/**
	 * Handle mu-plugin removal request.
	 *
	 * @return void
	 */
	public static function handle_remove_mu(): void {
		check_admin_referer( 'site_monitor_remove_mu' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'site-monitor-reporter' ) );
		}

		MuPluginInstaller::uninstall();

		wp_safe_redirect( admin_url( 'options-general.php?page=site-monitor-reporter&mu-removed=1' ) );
		exit();
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
