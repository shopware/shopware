import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport, assertScreenshot } from '@shopware-ag/acceptance-test-suite';

test('Visual: Administration settings page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminSettingsListing,
}) => {

    await test.step('Creates a screenshot of the settings overview page.', async () => {
        await ShopAdmin.goesTo(AdminSettingsListing.url());
        await setViewport(AdminSettingsListing.page, {
            waitForSelector: AdminSettingsListing.shopwareServicesLink,
        });
        await assertScreenshot(AdminSettingsListing.page, 'Settings-Overview.png');
    });
});
