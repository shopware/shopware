import { test, setViewport, assertScreenshot, expandAdminMenu } from '@fixtures/AcceptanceTest';

// The CMS layout builder this test drives collapses the admin menu on mount and never expands
// it back (see expandAdminMenu's doc comment). Undo that here - as afterEach, not a last line in
// the test body, so it still runs (and un-leaks the collapsed sidebar) even if this test fails
// partway through.
test.afterEach(async ({ page }) => {
    await expandAdminMenu(page);
});

test(
    'Visual: Create product listing layout flow',
    { tag: '@Visual' },
    async ({
        ShopAdmin,
        AdminSettingsListing,
        AdminLayoutListing,
        AdminLayoutCreate,
    }) => {
        test.slow();

        await test.step('Creates a screenshot of the layout listing page.', async () => {
            await ShopAdmin.goesTo(AdminLayoutListing.url());
            await setViewport(AdminSettingsListing.page, {
                waitForSelector: AdminLayoutListing.createNewLayoutButton,
            });
            await assertScreenshot(AdminLayoutListing.page, 'Layout-Listing.png');
        });

        await test.step('Creates screenshots of the layout creation pages.', async () => {
            await AdminLayoutListing.createNewLayoutButton.click();
            await ShopAdmin.expects(AdminLayoutCreate.listingPageButton).toBeVisible();
            await setViewport(AdminLayoutCreate.page, {
                waitForSelector: AdminLayoutCreate.cancelButton,
            });
            await assertScreenshot(AdminLayoutCreate.page, 'Layout-Create-Page-Types.png');

            await AdminLayoutCreate.listingPageButton.click();
            await ShopAdmin.expects(AdminLayoutCreate.backButton).toBeVisible();
            await setViewport(AdminLayoutCreate.page, {
                waitForSelector: AdminLayoutCreate.fullWidthButton,
            });
            await assertScreenshot(AdminLayoutCreate.page, 'Layout-Create-Section-Types.png');

            await AdminLayoutCreate.fullWidthButton.click();
            await ShopAdmin.expects(AdminLayoutCreate.layoutNameInput).toBeVisible();
            await setViewport(AdminLayoutCreate.page, {
                waitForSelector: AdminLayoutCreate.createLayoutButton,
            });
            await assertScreenshot(AdminLayoutCreate.page, 'Layout-Create-Layout-Name.png');
        });
    },
);
