import { test, assertScreenshot, setViewport, replaceElementsIndividually } from '@fixtures/AcceptanceTest';

test('Visual: Administration media page', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminMediaListing,
}) => {

    await test.step('Creates a screenshot of the media page.', async () => {
        await ShopAdmin.goesTo(AdminMediaListing.url());
        await setViewport(AdminMediaListing.page, {
            scrollableElementVertical: AdminMediaListing.scrollableElementVertical,
            additionalHeight: 100,
            waitForSelector: AdminMediaListing.mediaFolder('Product Media'),
        });
        await assertScreenshot(AdminMediaListing.page, 'Media-Listing.png');
    });

    await test.step('Creates a screenshot of an open media folder.', async () => {
        await AdminMediaListing.mediaFolder('Product Media').click();
        await ShopAdmin.expects(AdminMediaListing.emptyState).toBeVisible();
        await setViewport(AdminMediaListing.page, {
            scrollableElementVertical: AdminMediaListing.scrollableElementVertical,
        })
        await replaceElementsIndividually(AdminMediaListing.page, [
            { selector: AdminMediaListing.updatedAtDate, replaceWith: '1 January 1970 at 00:01'},
        ]);
        await assertScreenshot(AdminMediaListing.page, 'Media-Folder-Open.png');
    });
});
