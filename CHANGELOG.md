# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.3] - 2026-04-18

### Fixed

- DDEV `orchestrate` command failing on fresh clones and in CI by installing
  the `apermo/ddev-orchestrate` addon via a `pre-start` hook (#22)

### Added

- `.wp-env.json` for the E2E workflow to boot `wp-env` against the plugin

### Changed

- Pinned reusable GitHub Actions workflows to the floating `v0.4` tag
  (previously tracked `main`)

## [0.1.2] - 2026-04-05

### Changed

- DDEV docroot moved to `.ddev/wordpress/` subdirectory to keep project root clean
- Replaced pre-start hook with tracked `.gitkeep` for docroot directory

### Fixed

- PHPCS running out of memory by scanning WordPress core files in `.ddev/wordpress/`

## [0.1.1] - 2026-04-04

### Added

- Packagist and Codecov badges in README
- Published to Packagist as `apermo/site-bookkeeper-reporter`
- Link to Dashboard plugin in README

### Fixed

- Missing `wp_get_environment_type` stub in ReportPusherTest
- E2E settings test failing due to ambiguous `#submit` selector

## [0.1.0] - 2026-04-01

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

[0.1.3]: https://github.com/apermo/site-bookkeeper-reporter/compare/v0.1.2...v0.1.3
[0.1.2]: https://github.com/apermo/site-bookkeeper-reporter/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/apermo/site-bookkeeper-reporter/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/apermo/site-bookkeeper-reporter/releases/tag/v0.1.0
