import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration settings page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminSettingsListing,
}) => {

    await test.step('Creates a screenshot of the settings overview page.', async () => {
        await ShopAdmin.goesTo(AdminSettingsListing.url());
        await setViewport(AdminSettingsListing.page, {
          responseURL: 'api/search/sales-channel',
        });
        await expect(AdminSettingsListing.contentView).toHaveScreenshot('Settings-Overview.png');
    });
});
