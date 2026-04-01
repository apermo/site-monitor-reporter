<?php

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

/**
 * Custom fields filter and default field collectors.
 *
 * Applies the `site_monitor_custom_fields` filter to allow
 * third-party extensions, validates the shape of returned entries,
 * and provides built-in default fields.
 */
class CustomFields {

	/**
	 * Collect custom fields via the filter and validate.
	 *
	 * @return array<int, array{key: string, label: string, value: string, status?: string}>
	 */
	public static function collect(): array {
		/**
		 * Filters the custom fields included in the monitoring report.
		 *
		 * phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Public API hook.
		 *
		 * @param mixed[] $fields Custom fields.
		 *
		 * @return mixed[]
		 */
		$fields = apply_filters( 'site_monitor_custom_fields', [] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

		return \array_values( \array_filter( $fields, [ self::class, 'is_valid_field' ] ) );
	}

	/**
	 * Register the default fields hook.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		add_filter( 'site_monitor_custom_fields', [ self::class, 'collect_defaults' ] );
	}

	/**
	 * Collect built-in default custom fields.
	 *
	 * @param array<int, array<string, string>> $fields Existing fields.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function collect_defaults( array $fields ): array {
		$fields[] = self::get_comment_status();
		$fields[] = self::get_permalink_structure();
		$fields[] = self::get_robots_status();
		$fields[] = self::get_object_cache_status();

		$seo_field = self::get_seo_plugin();
		if ( $seo_field !== null ) {
			$fields[] = $seo_field;
		}

		$cache_plugins = self::get_cache_plugins();
		if ( $cache_plugins !== null ) {
			$fields[] = $cache_plugins;
		}

		return $fields;
	}

	/**
	 * Validate that a field entry has the required shape.
	 *
	 * @param mixed $field Field to validate.
	 *
	 * @return bool
	 */
	private static function is_valid_field( mixed $field ): bool {
		if ( ! \is_array( $field ) ) {
			return false;
		}

		return isset( $field['key'], $field['label'], $field['value'] )
			&& \is_string( $field['key'] )
			&& \is_string( $field['label'] )
			&& \is_string( $field['value'] );
	}

	/**
	 * Get comment status field.
	 *
	 * @return array{key: string, label: string, value: string}
	 */
	private static function get_comment_status(): array {
		$status     = (string) get_option( 'default_comment_status', 'closed' );
		$moderation = (string) get_option( 'comment_moderation', '0' );

		$value = $status === 'open' ? 'open' : 'closed';
		if ( $moderation === '1' ) {
			$value .= ', moderation enabled';
		}

		return [
			'key'   => 'comment_status',
			'label' => 'Comment Status',
			'value' => $value,
		];
	}

	/**
	 * Get permalink structure field.
	 *
	 * @return array{key: string, label: string, value: string}
	 */
	private static function get_permalink_structure(): array {
		$structure = (string) get_option( 'permalink_structure', '' );

		return [
			'key'   => 'permalink_structure',
			'label' => 'Permalink Structure',
			'value' => $structure !== '' ? $structure : 'Plain (default)',
		];
	}

	/**
	 * Get robots/search engine visibility field.
	 *
	 * @return array{key: string, label: string, value: string, status: string}
	 */
	private static function get_robots_status(): array {
		$public = (string) get_option( 'blog_public', '1' );

		return [
			'key'    => 'blog_public',
			'label'  => 'Search Engine Visibility',
			'value'  => $public === '1' ? 'Visible' : 'Discouraged',
			'status' => $public === '1' ? 'good' : 'warning',
		];
	}

	/**
	 * Get object cache status field.
	 *
	 * @return array{key: string, label: string, value: string, status: string}
	 */
	private static function get_object_cache_status(): array {
		$using_ext = wp_using_ext_object_cache();
		$backend   = 'none';

		if ( $using_ext ) {
			if ( \class_exists( 'Redis' ) ) {
				$backend = 'Redis';
			} elseif ( \class_exists( 'Memcached' ) ) {
				$backend = 'Memcached';
			} else {
				$backend = 'external';
			}
		}

		return [
			'key'    => 'object_cache',
			'label'  => 'Object Cache',
			'value'  => $using_ext ? $backend . ' (connected)' : 'none',
			'status' => $using_ext ? 'good' : 'info',
		];
	}

	/**
	 * Get SEO plugin field if one is active.
	 *
	 * @return array{key: string, label: string, value: string}|null
	 */
	private static function get_seo_plugin(): ?array {
		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			return [
				'key'   => 'seo_plugin',
				'label' => 'SEO Plugin',
				'value' => 'Yoast SEO',
			];
		}

		if ( is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ) {
			return [
				'key'   => 'seo_plugin',
				'label' => 'SEO Plugin',
				'value' => 'Rank Math',
			];
		}

		return null;
	}

	/**
	 * Get cache plugin field if one is active.
	 *
	 * @return array{key: string, label: string, value: string}|null
	 */
	private static function get_cache_plugins(): ?array {
		$cache_plugins = [
			'wp-super-cache/wp-cache.php'         => 'WP Super Cache',
			'w3-total-cache/w3-total-cache.php'   => 'W3 Total Cache',
			'wp-fastest-cache/wpFastestCache.php' => 'WP Fastest Cache',
			'litespeed-cache/litespeed-cache.php' => 'LiteSpeed Cache',
		];

		foreach ( $cache_plugins as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				return [
					'key'   => 'cache_plugin',
					'label' => 'Cache Plugin',
					'value' => $name,
				];
			}
		}

		return null;
	}
}
