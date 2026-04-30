import { expect, type Page } from '@playwright/test';
import { test } from '@fixtures/AcceptanceTest';

const EXPECTED_COMMERCIAL_TOGGLE = process.env.ADMIN_PLUGIN_COMPATIBILITY_EXPECTED_TOGGLE ||
    'TEXT_TO_IMAGE_GENERATION-6841914';

test('Commercial Administration boot smoke', { tag: ['@admin-plugin-compatibility', '@compatibility-boot'] }, async ({
    ShopAdmin,
    AdminDashboard,
}) => {
    const page = AdminDashboard.page;
    const runtimeErrors = collectRuntimeErrors(page);

    await ShopAdmin.goesTo(AdminDashboard.url());
    await expect(AdminDashboard.contentView).toBeVisible();

    await expectCommercialState(page);
    expect(runtimeErrors).toEqual([]);
});

test('Commercial media library smoke', { tag: ['@admin-plugin-compatibility', '@compatibility-sw-media-library'] }, async ({
    ShopAdmin,
    AdminMediaListing,
}) => {
    const page = AdminMediaListing.page;
    const runtimeErrors = collectRuntimeErrors(page);

    await ShopAdmin.goesTo(AdminMediaListing.url());
    await expect(AdminMediaListing.mediaFolder('Product Media')).toBeVisible();
    await expect(page.locator('.sw-media-libary__generate-image-button')).toBeVisible();

    await expectCommercialState(page);
    expect(runtimeErrors).toEqual([]);
});

test('Commercial settings search smoke', { tag: ['@admin-plugin-compatibility', '@compatibility-sw-settings-search'] }, async ({
    ShopAdmin,
    AdminDashboard,
}) => {
    const page = AdminDashboard.page;
    const runtimeErrors = collectRuntimeErrors(page);

    await ShopAdmin.goesTo('#/sw/settings/search/index');
    await expect(page.locator('.sw-settings-search')).toBeVisible();
    await expect(page.locator('.sw-settings-search__aisearch-tab')).toBeVisible();

    await expectCommercialState(page);
    expect(runtimeErrors).toEqual([]);
});

async function expectCommercialState(page: Page): Promise<void> {
    const commercialState = await page.evaluate((expectedToggle) => {
        const shopware = (globalThis as {
            Shopware?: {
                Context?: { app?: { config?: { bundles?: Record<string, unknown> } } };
                License?: { get?: unknown };
            };
        }).Shopware;
        const bundles = shopware?.Context?.app?.config?.bundles ?? {};
        const bundleNames = Object.keys(bundles);
        const licenseGet = shopware?.License?.get;

        return {
            hasCommercialBundle: bundleNames.includes('swag-commercial'),
            hasLicenseApi: typeof licenseGet === 'function',
            expectedToggleActive: typeof licenseGet === 'function' && Boolean(licenseGet(expectedToggle)),
        };
    }, EXPECTED_COMMERCIAL_TOGGLE);

    expect(commercialState).toEqual({
        hasCommercialBundle: true,
        hasLicenseApi: true,
        expectedToggleActive: true,
    });
}

function collectRuntimeErrors(page: Page): string[] {
    const runtimeErrors: string[] = [];

    page.on('pageerror', (error) => {
        runtimeErrors.push(`pageerror: ${error.message}`);
    });

    page.on('console', (message) => {
        if (message.type() === 'error') {
            runtimeErrors.push(`console: ${message.text()}`);
        }
    });

    page.on('requestfailed', (request) => {
        const url = request.url();

        if (url.includes('/bundles/') || url.includes('/admin')) {
            runtimeErrors.push(`requestfailed: ${url} ${request.failure()?.errorText ?? ''}`.trim());
        }
    });

    return runtimeErrors;
}
