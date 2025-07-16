import { test, expect } from '@fixtures/AcceptanceTest';
import path from 'path';
test('Visual: Administration settings page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminSettingsListing,
    SetScreenshotDimensions,
}) => {

    await test.step('Creates a screenshot of the settings overview page.', async () => {
        await ShopAdmin.goesTo(AdminSettingsListing.url());
        await SetScreenshotDimensions(AdminSettingsListing.page, {
          responseURL: 'api/search/sales-channel',
        });
        await expect(AdminSettingsListing.page.locator('.sw-desktop__content'))
            .toHaveScreenshot('Settings-Overview.png');
    });
});
