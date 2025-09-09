import { test, assertScreenshot, setViewport, replaceElements } from '@fixtures/AcceptanceTest';

test('Visual: Administration media page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminMediaListing,
}) => {

    await test.step('Creates a screenshot of the media page.', async () => {
        await ShopAdmin.goesTo(AdminMediaListing.url());
        await setViewport(AdminMediaListing.page, {
            scrollableElementVertical: AdminMediaListing.page.locator('.sw-media-library__scroll-container'),
            additionalHeight: 100,
            waitForSelector: AdminMediaListing.page.locator('.sw-media-folder-item').getByText('Product Media'),
        });
        await assertScreenshot(AdminMediaListing.page, 'Media-Listing.png');
    });
});
