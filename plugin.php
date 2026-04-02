<?php
/**
 * Plugin Name: Site Bookkeeper Reporter
 * Description: Pushes site health data to a central monitoring hub.
 * Version:     0.1.0
 * Author:      Christoph Daum
 * Author URI:  https://apermo.de
 * License:     GPL-2.0-or-later
 * Text Domain: site-bookkeeper-reporter
 * Requires at least: 6.2
 * Requires PHP: 8.2
 * Network:      true
 */

declare(strict_types=1);

namespace Apermo\SiteBookkeeperReporter;

use WP_CLI;

\defined( 'ABSPATH' ) || exit();

// Prevent double-loading when active as both plugin and mu-plugin.
if ( \defined( 'SITE_BOOKKEEPER_REPORTER_LOADED' ) ) {
	return;
}

\define( 'SITE_BOOKKEEPER_REPORTER_LOADED', true );

require_once __DIR__ . '/vendor/autoload.php';

Plugin::init( __FILE__ );

if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
	WP_CLI::add_command( 'bookkeeper-reporter', CLI\Commands::class );
}
