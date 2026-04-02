<?php

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

/**
 * Settings page for configuring the monitoring hub connection.
 *
 * Settings can be defined via constants in wp-config.php:
 * - SITE_BOOKKEEPER_HUB_URL: The URL of the monitoring hub.
 * - SITE_BOOKKEEPER_TOKEN: The authentication token for the hub.
 *
 * When constants are defined, they take precedence over database options
 * and the corresponding fields are displayed as read-only.
 *
 * When network-activated on multisite, settings are stored as site options
 * and the settings page appears under Network Admin > Settings.
 */
class Settings {

	/**
	 * Check if the plugin is running in network mode.
	 *
	 * @return bool
	 */
	public static function is_network_mode(): bool {
		return MultisiteDetector::is_multisite()
			&& MultisiteDetector::is_network_activated();
	}

	/**
	 * Register hooks for the settings page.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		if ( self::is_network_mode() ) {
			add_action( 'network_admin_menu', [ self::class, 'add_network_menu_page' ] );
		} else {
			add_action( 'admin_menu', [ self::class, 'add_menu_page' ] );
		}

		add_action( 'admin_init', [ self::class, 'register_settings' ] );
		add_action( 'admin_post_site_bookkeeper_install_mu', [ self::class, 'handle_install_mu' ] );
		add_action( 'admin_post_site_bookkeeper_remove_mu', [ self::class, 'handle_remove_mu' ] );
	}

	/**
	 * Add the settings page under the Settings menu (single-site).
	 *
	 * @return void
	 */
	public static function add_menu_page(): void {
		add_options_page(
			'Site Bookkeeper Reporter',
			'Site Bookkeeper',
			'manage_options',
			'site-bookkeeper-reporter',
			[ self::class, 'render_page' ],
		);
	}

	/**
	 * Add the settings page under Network Admin > Settings.
	 *
	 * @return void
	 */
	public static function add_network_menu_page(): void {
		add_submenu_page(
			'settings.php',
			'Site Bookkeeper Reporter',
			'Site Bookkeeper',
			'manage_network_options',
			'site-bookkeeper-reporter',
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
			'site_bookkeeper_reporter',
			'site_bookkeeper_hub_url',
			[
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => '',
			],
		);

		register_setting(
			'site_bookkeeper_reporter',
			'site_bookkeeper_token',
			[
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
		);

		add_settings_section(
			'site_bookkeeper_reporter_main',
			'Connection Settings',
			[ self::class, 'render_section' ],
			'site-bookkeeper-reporter',
		);

		add_settings_field(
			'site_bookkeeper_hub_url',
			'Hub URL',
			[ self::class, 'render_hub_url_field' ],
			'site-bookkeeper-reporter',
			'site_bookkeeper_reporter_main',
		);

		add_settings_field(
			'site_bookkeeper_token',
			'Token',
			[ self::class, 'render_token_field' ],
			'site-bookkeeper-reporter',
			'site_bookkeeper_reporter_main',
		);
	}

	/**
	 * Get the hub URL, preferring the constant over the option.
	 *
	 * @return string
	 */
	public static function get_hub_url(): string {
		if ( self::is_hub_url_constant() ) {
			return \SITE_BOOKKEEPER_HUB_URL;
		}

		if ( self::is_network_mode() ) {
			return (string) get_site_option( 'site_bookkeeper_hub_url', '' );
		}

		return (string) get_option( 'site_bookkeeper_hub_url', '' );
	}

	/**
	 * Get the token, preferring the constant over the option.
	 *
	 * @return string
	 */
	public static function get_token(): string {
		if ( self::is_token_constant() ) {
			return \SITE_BOOKKEEPER_TOKEN;
		}

		if ( self::is_network_mode() ) {
			return (string) get_site_option( 'site_bookkeeper_token', '' );
		}

		return (string) get_option( 'site_bookkeeper_token', '' );
	}

	/**
	 * Check if the hub URL is defined as a constant.
	 *
	 * @return bool
	 */
	public static function is_hub_url_constant(): bool {
		return \defined( 'SITE_BOOKKEEPER_HUB_URL' );
	}

	/**
	 * Check if the token is defined as a constant.
	 *
	 * @return bool
	 */
	public static function is_token_constant(): bool {
		return \defined( 'SITE_BOOKKEEPER_TOKEN' );
	}

	/**
	 * Handle network admin settings form submission.
	 *
	 * @return void
	 */
	public static function handle_network_settings(): void {
		check_admin_referer( 'site_bookkeeper_reporter_network' );

		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'site-bookkeeper-reporter' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_admin_referer.
		$hub_url = isset( $_POST['site_bookkeeper_hub_url'] )
			? esc_url_raw( (string) wp_unslash( $_POST['site_bookkeeper_hub_url'] ) )
			: '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via check_admin_referer.
		$token = isset( $_POST['site_bookkeeper_token'] )
			? sanitize_text_field( (string) wp_unslash( $_POST['site_bookkeeper_token'] ) )
			: '';

		update_site_option( 'site_bookkeeper_hub_url', $hub_url );
		update_site_option( 'site_bookkeeper_token', $token );

		wp_safe_redirect(
			network_admin_url( 'settings.php?page=site-bookkeeper-reporter&updated=true' ),
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		if ( self::is_network_mode() ) {
			self::render_network_page();
			return;
		}

		?>
		<div class="wrap">
			<h1>Site Bookkeeper Reporter</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'site_bookkeeper_reporter' );
				do_settings_sections( 'site-bookkeeper-reporter' );
				submit_button();
				?>
			</form>

			<h2>MU-Plugin Loader</h2>
			<?php self::render_mu_plugin_status(); ?>
		</div>
		<?php
	}

	/**
	 * Render the network admin settings page.
	 *
	 * @return void
	 */
	private static function render_network_page(): void {
		?>
		<div class="wrap">
			<h1>Site Bookkeeper Reporter</h1>
			<form method="post" action="edit.php?action=site_bookkeeper_network_settings">
				<?php
				wp_nonce_field( 'site_bookkeeper_reporter_network' );
				do_settings_sections( 'site-bookkeeper-reporter' );
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
			echo '<input type="hidden" name="action" value="site_bookkeeper_remove_mu" />';
			wp_nonce_field( 'site_bookkeeper_remove_mu' );
			submit_button( 'Remove MU-Plugin Loader', 'delete', 'submit', false );
			echo '</form>';
		} else {
			echo '<p><span class="dashicons dashicons-warning" style="color:#dba617;"></span> ';
			echo 'MU-Plugin loader is not installed.</p>';

			\printf(
				'<form method="post" action="%s">',
				esc_url( $admin_url ),
			);
			echo '<input type="hidden" name="action" value="site_bookkeeper_install_mu" />';
			wp_nonce_field( 'site_bookkeeper_install_mu' );
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
		check_admin_referer( 'site_bookkeeper_install_mu' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'site-bookkeeper-reporter' ) );
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
					esc_url( admin_url( 'options-general.php?page=site-bookkeeper-reporter' ) ),
				),
			);
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=site-bookkeeper-reporter&mu-installed=1' ) );
		exit();
	}

	/**
	 * Handle mu-plugin removal request.
	 *
	 * @return void
	 */
	public static function handle_remove_mu(): void {
		check_admin_referer( 'site_bookkeeper_remove_mu' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'site-bookkeeper-reporter' ) );
		}

		MuPluginInstaller::uninstall();

		wp_safe_redirect( admin_url( 'options-general.php?page=site-bookkeeper-reporter&mu-removed=1' ) );
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
			'<input type="url" name="site_bookkeeper_hub_url" value="%s" class="regular-text" %s />',
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">Defined via <code>SITE_BOOKKEEPER_HUB_URL</code> constant.</p>';
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
			'<input type="password" name="site_bookkeeper_token" value="%s" class="regular-text" %s />',
			esc_attr( $value ),
			$disabled ? 'disabled="disabled"' : '',
		);

		if ( $disabled ) {
			echo '<p class="description">Defined via <code>SITE_BOOKKEEPER_TOKEN</code> constant.</p>';
		}
	}
}
