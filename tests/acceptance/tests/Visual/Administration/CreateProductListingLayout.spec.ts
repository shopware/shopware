import { test, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';
import { expandAdminMenu } from '@helpers/admin-menu-helpers';

// This flow visits the CMS layout builder, which collapses the admin menu on mount and never
// expands it back (see expandAdminMenu's doc comment). Restore it once this test is done so it
// doesn't leak into whichever @Visual spec runs next in the same worker. Runs even if the test
// above fails, so a failure here can't still leak the collapsed sidebar into later specs.
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
        AdminListingPageLayoutDetail,
        TestDataService,
    }) => {
        test.slow();
        let createdLayoutId: string;

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

            await AdminLayoutCreate.layoutNameInput.fill('test');
            const waitForSearchResponse = AdminLayoutCreate.page.waitForResponse(
                (response) => response.url().includes('/api/search/cms-page') && response.request().method() === 'POST',
            );
            await AdminLayoutCreate.createLayoutButton.click();
            const searchResponse = await waitForSearchResponse;
            const body = await searchResponse.json();
            createdLayoutId = body.data[0].id;
            await ShopAdmin.expects(createdLayoutId).not.toBeNull();
            TestDataService.addCreatedRecord('cms-page', createdLayoutId);
            await ShopAdmin.expects(AdminListingPageLayoutDetail.settingsButton).toBeVisible();
        });
    },
);
