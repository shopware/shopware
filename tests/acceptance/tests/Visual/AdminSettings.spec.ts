import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';
test('Visual: Administration settings page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminCategories,
    AdminSettingsListing,
}) => {

    await test.step('Creates a screenshot of the settings overview page.', async () => {

        await ShopAdmin.goesTo(AdminSettingsListing.url());
        await ShopAdmin.expects(AdminSettingsListing.header).toHaveText('Settings');

        await AdminCategories.page.setViewportSize({ width: 1440, height: 1440});

        await expect(AdminCategories.page.locator('.sw-desktop__content')).toHaveScreenshot({
            stylePath: path.resolve('./tests/Visual/screenshot.css'),
        });
    });
});
