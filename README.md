# Site Bookkeeper Reporter

A monitoring tool for your WordPress Sites — Reporter Plugin

[![PHP CI](https://github.com/apermo/site-bookkeeper-reporter/actions/workflows/ci.yml/badge.svg)](https://github.com/apermo/site-bookkeeper-reporter/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2+-blue.svg)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/apermo/site-bookkeeper-reporter)](https://packagist.org/packages/apermo/site-bookkeeper-reporter)

WordPress plugin that collects site health data (core version, plugins, themes, users, roles, custom fields)
and pushes it to a central [Site Bookkeeper Hub](https://github.com/apermo/site-bookkeeper-hub) on a
twice-daily cron schedule.

## Requirements

- PHP 8.2+
- WordPress 6.2+
- A running [Site Bookkeeper Hub](https://github.com/apermo/site-bookkeeper-hub) instance

## Installation

Install via Composer:

```bash
composer require apermo/site-bookkeeper-reporter
```

Activate the plugin and configure the hub URL and token in **Settings > Site Bookkeeper**, or define
constants in `wp-config.php`:

```php
define( 'SITE_BOOKKEEPER_HUB_URL', 'https://monitor.example.tld' );
define( 'SITE_BOOKKEEPER_TOKEN', 'your-site-token-here' );
```

### HTTPS Requirement

The hub URL **must use HTTPS**. The plugin rejects HTTP URLs in the settings form and refuses to push reports
over plain HTTP. This protects your authentication token from being transmitted in clear text.

For local development (e.g. DDEV), you can bypass this check by defining:

```php
define( 'SITE_BOOKKEEPER_ALLOW_HTTP', true );
```

## WP-CLI Commands

```bash
wp bookkeeper-reporter report          # Push a report immediately
wp bookkeeper-reporter status          # Preview collected data without pushing
wp bookkeeper-reporter test            # Test connection to the hub
wp bookkeeper-reporter network-report  # Push network report (multisite)
wp bookkeeper-reporter network-status  # Preview network data (multisite)
```

### Bulk Setup via WP-CLI

If you manage many WordPress sites (e.g. via [wp-cli/config-command](https://developer.wordpress.org/cli/commands/config/)
or a shared deployment tool), you can script the setup across all of them.

**Example: register and configure all sites managed by WP-CLI's `@alias` system:**

```bash
#!/usr/bin/env bash
set -euo pipefail

HUB_URL="https://monitor.example.tld"
HUB_CLI="php /path/to/site-bookkeeper-hub/bin/manage.php"

# List all WP-CLI aliases (one per managed site)
for alias in $(wp cli alias list --format=json | jq -r 'keys[]'); do
    echo "--- Setting up ${alias} ---"

    site_url=$(wp @"${alias}" option get siteurl)

    # Register the site on the hub and capture the token
    token=$($HUB_CLI site:add "${site_url}" --label="${alias}" 2>&1 | grep "Bearer token" -A1 | tail -1 | tr -d ' ')

    # Write constants to wp-config.php
    wp @"${alias}" config set SITE_BOOKKEEPER_HUB_URL "${HUB_URL}" --type=constant --raw
    wp @"${alias}" config set SITE_BOOKKEEPER_TOKEN "${token}" --type=constant --raw

    # Activate the plugin and push an initial report
    wp @"${alias}" plugin activate site-bookkeeper-reporter
    wp @"${alias}" bookkeeper-reporter report

    echo "    ${site_url} → registered and reporting"
done
```

**Example: loop over sites listed in a simple text file:**

```bash
#!/usr/bin/env bash
set -euo pipefail

HUB_URL="https://monitor.example.tld"
HUB_CLI="php /path/to/site-bookkeeper-hub/bin/manage.php"
SITES_FILE="./sites.txt"  # One SSH path per line, e.g. user@host:/var/www/site

while IFS= read -r ssh_path; do
    [[ -z "${ssh_path}" || "${ssh_path}" == \#* ]] && continue

    echo "--- Setting up ${ssh_path} ---"

    site_url=$(wp --ssh="${ssh_path}" option get siteurl)
    token=$($HUB_CLI site:add "${site_url}" --label="${ssh_path}" 2>&1 | grep "Bearer token" -A1 | tail -1 | tr -d ' ')

    wp --ssh="${ssh_path}" config set SITE_BOOKKEEPER_HUB_URL "${HUB_URL}" --type=constant --raw
    wp --ssh="${ssh_path}" config set SITE_BOOKKEEPER_TOKEN "${token}" --type=constant --raw
    wp --ssh="${ssh_path}" plugin activate site-bookkeeper-reporter
    wp --ssh="${ssh_path}" bookkeeper-reporter report

    echo "    ${site_url} → registered and reporting"
done < "${SITES_FILE}"
```

## Multisite Support

When network-activated, the plugin:

- Stores settings network-wide (one hub URL + token for all subsites)
- Main site pushes reports for all subsites via `switch_to_blog()` on cron
- Main site sends an additional network-level report (network plugins, super admins, subsite list)

For multisite, register a **network** on the hub instead of individual sites:

```bash
php bin/manage.php network:add https://network.example.tld --label="My Network"
```

Subsites auto-register on their first report.

## Custom Fields

Extend the report payload with custom data via filter:

```php
add_filter( 'site_bookkeeper_custom_fields', function ( array $fields ): array {
    $fields[] = [
        'key'    => 'my_check',
        'label'  => 'My Custom Check',
        'value'  => 'All good',
        'status' => 'good', // good, warning, critical, or omit
    ];
    return $fields;
} );
```

## Development

```bash
composer install
composer cs              # Run PHPCS
composer cs:fix          # Fix PHPCS violations
composer analyse         # Run PHPStan
composer test:unit       # Run unit tests
```

## License

[GPL-2.0-or-later](LICENSE)
