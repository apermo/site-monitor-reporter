const { test, expect } = require('@playwright/test');

const SETTINGS_URL = '/wp-admin/options-general.php?page=site-bookkeeper-reporter';

test('settings page loads with correct heading', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	await expect(page.locator('h1')).toContainText('Site Bookkeeper Reporter');
});

test('settings page shows hub URL field', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	await expect(page.locator('input[name="site_bookkeeper_hub_url"]')).toBeVisible();
});

test('settings page shows token field', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	await expect(page.locator('input[name="site_bookkeeper_token"]')).toBeVisible();
});

test('saving settings shows success message', async ({ page }) => {
	await page.goto(SETTINGS_URL);

	await page.locator('input[name="site_bookkeeper_hub_url"]').fill('https://monitor.example.tld');
	await page.locator('input[name="site_bookkeeper_token"]').fill('test-token-123');
	await page.locator('#submit').click();

	await expect(page.locator('#setting-error-settings_updated, .notice-success')).toBeVisible();
});

test('saved settings are persisted', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	await expect(page.locator('input[name="site_bookkeeper_hub_url"]')).toHaveValue('https://monitor.example.tld');
});

test('mu-plugin section is visible', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	await expect(page.locator('h2', { hasText: 'MU-Plugin Loader' })).toBeVisible();
});

test('mu-plugin section has action button', async ({ page }) => {
	await page.goto(SETTINGS_URL);
	const installBtn = page.locator('input[value="Install MU-Plugin Loader"]');
	const removeBtn = page.locator('input[value="Remove MU-Plugin Loader"]');

	// Either install or remove button should be present
	const hasInstall = await installBtn.count();
	const hasRemove = await removeBtn.count();
	expect(hasInstall + hasRemove).toBeGreaterThan(0);
});
