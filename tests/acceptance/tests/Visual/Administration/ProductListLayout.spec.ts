import { test, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Visual: Shopping experiences pages', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminSettingsListing,
    AdminLayoutListing,
    AdminLayoutCreate,
    AdminListingPageLayoutDetail,
    AdminApiContext,
}) => {
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
        const waitForSearchResponse = AdminLayoutCreate.page.waitForResponse(response =>
            response.url().includes('/api/search/cms-page') && response.request().method() === 'POST'
        );
        await AdminLayoutCreate.createLayoutButton.click();
        const searchResponse = await waitForSearchResponse;
        const body = await searchResponse.json();
        createdLayoutId = body.data[0].id;
        await ShopAdmin.expects(createdLayoutId).not.toBeNull();
    });

    await test.step('Creates screenshots of the shopping list layout detail page.', async () => {
        await ShopAdmin.expects(AdminListingPageLayoutDetail.settingsButton).toBeVisible();
        await AdminListingPageLayoutDetail.settingsButton.click();
        await setViewport(AdminListingPageLayoutDetail.page, {
            waitForSelector: AdminListingPageLayoutDetail.sidebarTitle,
            scrollableElementVertical: '.sw-cms-detail__stage',
        });
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Settings-Tab.png');

        await AdminListingPageLayoutDetail.blocksButton.click();
        await setViewport(AdminListingPageLayoutDetail.page, {
            waitForSelector: AdminListingPageLayoutDetail.sidebarTitle,
            scrollableElementVertical: '.sw-cms-detail__stage',
        });
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Blocks-Tab.png');

        await AdminListingPageLayoutDetail.layoutAssignmentButton.click();
        await setViewport(AdminListingPageLayoutDetail.page, {
            waitForSelector: AdminListingPageLayoutDetail.sidebarTitle,
            scrollableElementVertical: '.sw-cms-detail__stage',
        });
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Layout-Assignment-Tab.png');

        await AdminListingPageLayoutDetail.navigatorButton.click();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.loaderButton).not.toBeVisible();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.saveButton).toBeVisible();
        await setViewport(AdminListingPageLayoutDetail.page, {
            waitForSelector: AdminListingPageLayoutDetail.sidebarTitle,
            scrollableElementVertical: '.sw-cms-detail__stage',
             });
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Navigator-Tab.png');
    });

    await test.step('Delete the created layout', async () => {
        await AdminApiContext.delete(`cms-page/${createdLayoutId}`);
    });
});