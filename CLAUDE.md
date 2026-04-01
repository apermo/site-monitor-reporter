# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that pushes site health data to a central monitoring hub. Collects WP core, plugin, and theme
versions, update availability, custom fields, user info, and roles, then sends the report via HTTP POST.

**PHP 8.2+ minimum.** Strict types everywhere (`declare(strict_types=1)`).

## Architecture

- `plugin.php` -- main entry point (plugin header, autoloader, `Plugin::init()`)
- `src/Plugin.php` -- bootstrap class (hooks, activation, deactivation)
- `src/` -- PSR-4 root (`Apermo\SiteMonitorReporter`)
- `tests/Unit/` -- PHPUnit + Brain Monkey unit tests
- `tests/Integration/` -- WP integration tests
- `uninstall.php` -- cleanup on uninstall

### Key conventions

- PSR-4 autoloading under `src/`
- Coding standards: `apermo/apermo-coding-standards` (PHPCS)
- Static analysis: `apermo/phpstan-wordpress-rules` + `szepeviktor/phpstan-wordpress`
- Testing: PHPUnit + Brain Monkey + Yoast PHPUnit Polyfills
- Test suites: `tests/Unit/` and `tests/Integration/`
- Example domains: always use `.tld` TLD (e.g. `https://monitor.example.tld`)
- Constants: `SITE_MONITOR_HUB_URL`, `SITE_MONITOR_TOKEN`

## Commands

```bash
composer cs              # Run PHPCS
composer cs:fix          # Fix PHPCS violations
composer analyse         # Run PHPStan
composer test            # Run all tests
composer test:unit       # Run unit tests only
composer test:integration # Run integration tests only
npm run test:e2e         # Run Playwright E2E tests
npm run test:e2e:ui      # Run E2E tests with UI
```

## Local Development (DDEV)

```bash
ddev start && ddev orchestrate   # Full WordPress environment
```

- Uses `apermo/ddev-orchestrate` addon
- Project type is `php` (not `wordpress`), so WP-CLI uses a custom `ddev wp` command wrapper
- Bind-mounts repo into `wp-content/plugins/`

## Git Hooks

Pre-commit hook runs PHPCS and PHPStan on staged files. Enable with:

```bash
git config core.hooksPath .githooks
```

## CI (GitHub Actions)

- `ci.yml` -- PHPCS + PHPStan + PHPUnit across PHP 8.2, 8.3, 8.4
- `integration.yml` -- WP integration tests (real WP + MySQL, multisite matrix)
- `e2e.yml` -- Playwright E2E tests against running WordPress
- `wp-beta.yml` -- Nightly WP beta/RC compatibility check
- `release.yml` -- CHANGELOG-driven releases
- `pr-validation.yml` -- conventional commit and changelog checks
