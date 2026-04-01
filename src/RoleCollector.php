<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

/**
 * Collects role information for the monitoring report.
 *
 * Reports all roles with their capabilities, flags custom roles
 * (not in WP core defaults), and detects modified core roles.
 */
class RoleCollector {

	/**
	 * WordPress core default role slugs.
	 *
	 * @var array<string>
	 */
	private const CORE_ROLES = [
		'administrator',
		'editor',
		'author',
		'contributor',
		'subscriber',
	];

	/**
	 * Collect all roles with metadata.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect(): array {
		$wp_roles = wp_roles();
		$result   = [];

		foreach ( $wp_roles->roles as $slug => $role_data ) {
			$is_core = \in_array( $slug, self::CORE_ROLES, true );

			// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- Data transfer payload.
			$result[] = [
				'slug'         => $slug,
				'name'         => $role_data['name'] ?? $slug,
				'is_custom'    => ! $is_core,
				'is_modified'  => $is_core ? self::is_modified( $slug, $role_data['capabilities'] ?? [] ) : false,
				'capabilities' => $role_data['capabilities'] ?? [],
			];
		}

		return $result;
	}

	/**
	 * Check if a core role has been modified from defaults.
	 *
	 * Compares the current capabilities against the WordPress
	 * defaults to detect added or removed capabilities.
	 *
	 * @param string              $slug         Role slug.
	 * @param array<string, bool> $capabilities Current capabilities.
	 *
	 * @return bool
	 */
	private static function is_modified( string $slug, array $capabilities ): bool {
		$defaults = self::get_core_defaults();

		if ( ! isset( $defaults[ $slug ] ) ) {
			return false;
		}

		$default_caps = $defaults[ $slug ];

		// Compare sorted keys to detect additions/removals.
		$current_keys = \array_keys( \array_filter( $capabilities ) );
		$default_keys = \array_keys( \array_filter( $default_caps ) );

		\sort( $current_keys );
		\sort( $default_keys );

		return $current_keys !== $default_keys;
	}

	/**
	 * Get the WordPress core default capabilities per role.
	 *
	 * @return array<string, array<string, bool>>
	 */
	private static function get_core_defaults(): array {
		return [
			'administrator' => self::get_administrator_defaults(),
			'editor'        => self::get_editor_defaults(),
			'author'        => self::get_author_defaults(),
			'contributor'   => self::get_contributor_defaults(),
			'subscriber'    => self::get_subscriber_defaults(),
		];
	}

	// phpcs:disable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength

	/**
	 * Get administrator default capabilities.
	 *
	 * @return array<string, bool>
	 */
	private static function get_administrator_defaults(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- WP core defaults listing.
		return [
			'switch_themes'          => true,
			'edit_themes'            => true,
			'activate_plugins'       => true,
			'edit_plugins'           => true,
			'edit_users'             => true,
			'edit_files'             => true,
			'manage_options'         => true,
			'moderate_comments'      => true,
			'manage_categories'      => true,
			'manage_links'           => true,
			'upload_files'           => true,
			'import'                 => true,
			'unfiltered_html'        => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'edit_pages'             => true,
			'read'                   => true,
			'level_10'               => true,
			'level_9'                => true,
			'level_8'                => true,
			'level_7'                => true,
			'level_6'                => true,
			'level_5'                => true,
			'level_4'                => true,
			'level_3'                => true,
			'level_2'                => true,
			'level_1'                => true,
			'level_0'                => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'publish_pages'          => true,
			'delete_pages'           => true,
			'delete_others_pages'    => true,
			'delete_published_pages' => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'delete_private_posts'   => true,
			'edit_private_posts'     => true,
			'read_private_posts'     => true,
			'delete_private_pages'   => true,
			'edit_private_pages'     => true,
			'read_private_pages'     => true,
			'delete_users'           => true,
			'create_users'           => true,
			'unfiltered_upload'      => true,
			'edit_dashboard'         => true,
			'update_plugins'         => true,
			'delete_plugins'         => true,
			'install_plugins'        => true,
			'update_themes'          => true,
			'install_themes'         => true,
			'update_core'            => true,
			'list_users'             => true,
			'remove_users'           => true,
			'promote_users'          => true,
			'edit_theme_options'     => true,
			'delete_themes'          => true,
			'export'                 => true,
		];
	}

	// phpcs:enable SlevomatCodingStandard.Functions.FunctionLength.FunctionLength

	/**
	 * Get editor default capabilities.
	 *
	 * @return array<string, bool>
	 */
	private static function get_editor_defaults(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeysError -- WP core defaults listing.
		return [
			'moderate_comments'      => true,
			'manage_categories'      => true,
			'manage_links'           => true,
			'upload_files'           => true,
			'unfiltered_html'        => true,
			'edit_posts'             => true,
			'edit_others_posts'      => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'edit_pages'             => true,
			'read'                   => true,
			'level_7'                => true,
			'level_6'                => true,
			'level_5'                => true,
			'level_4'                => true,
			'level_3'                => true,
			'level_2'                => true,
			'level_1'                => true,
			'level_0'                => true,
			'edit_others_pages'      => true,
			'edit_published_pages'   => true,
			'publish_pages'          => true,
			'delete_pages'           => true,
			'delete_others_pages'    => true,
			'delete_published_pages' => true,
			'delete_posts'           => true,
			'delete_others_posts'    => true,
			'delete_published_posts' => true,
			'delete_private_posts'   => true,
			'edit_private_posts'     => true,
			'read_private_posts'     => true,
			'delete_private_pages'   => true,
			'edit_private_pages'     => true,
			'read_private_pages'     => true,
		];
	}

	/**
	 * Get author default capabilities.
	 *
	 * @return array<string, bool>
	 */
	private static function get_author_defaults(): array {
		// phpcs:ignore Apermo.DataStructures.ArrayComplexity.TooManyKeys -- WP core defaults listing.
		return [
			'upload_files'           => true,
			'edit_posts'             => true,
			'edit_published_posts'   => true,
			'publish_posts'          => true,
			'read'                   => true,
			'level_2'                => true,
			'level_1'                => true,
			'level_0'                => true,
			'delete_posts'           => true,
			'delete_published_posts' => true,
		];
	}

	/**
	 * Get contributor default capabilities.
	 *
	 * @return array<string, bool>
	 */
	private static function get_contributor_defaults(): array {
		return [
			'edit_posts'   => true,
			'read'         => true,
			'level_1'      => true,
			'level_0'      => true,
			'delete_posts' => true,
		];
	}

	/**
	 * Get subscriber default capabilities.
	 *
	 * @return array<string, bool>
	 */
	private static function get_subscriber_defaults(): array {
		return [
			'read'    => true,
			'level_0' => true,
		];
	}
}
