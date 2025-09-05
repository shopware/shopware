import { test, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

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
