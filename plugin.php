<?php
/**
 * Plugin Name: Site Monitor Reporter
 * Description: Pushes site health data to a central monitoring hub.
 * Version:     0.1.0
 * Author:      Christoph Daum
 * Author URI:  https://apermo.de
 * License:     GPL-2.0-or-later
 * Text Domain: site-monitor-reporter
 * Requires at least: 6.2
 * Requires PHP: 8.2
 */

declare(strict_types=1);

namespace Apermo\SiteMonitorReporter;

\defined( 'ABSPATH' ) || exit();

// Prevent double-loading when active as both plugin and mu-plugin.
if ( \defined( 'SITE_MONITOR_REPORTER_LOADED' ) ) {
	return;
}

\define( 'SITE_MONITOR_REPORTER_LOADED', true );

require_once __DIR__ . '/vendor/autoload.php';

Plugin::init( __FILE__ );
