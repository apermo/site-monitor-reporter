# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-04-04

### Added

- Packagist and Codecov badges in README
- Published to Packagist as `apermo/site-bookkeeper-reporter`
- Link to Dashboard plugin in README

### Fixed

- Missing `wp_get_environment_type` stub in ReportPusherTest
- E2E settings test failing due to ambiguous `#submit` selector

## [0.1.0] - 2026-03-15

### Added

- Data collection for WordPress environment, plugins, themes, users, and roles
- Report pushing to central Site Bookkeeper Hub via JSON API
- Version tracking with `last_updated` timestamps
- Custom fields filter (`site_bookkeeper_custom_fields`)
- WP-CLI commands (`report`, `status`, `test`, `network-report`, `network-status`)
- Settings page with HTTPS enforcement for hub URL
- MU-plugin self-installer
- Multisite support: network-wide settings, network data collection, subsite cron scheduling
- Overdue detection and admin notices
- E2E and unit test suites

[0.1.1]: https://github.com/apermo/site-bookkeeper-reporter/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/apermo/site-bookkeeper-reporter/releases/tag/v0.1.0
