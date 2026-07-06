import { test, setViewport, assertScreenshot, hideElements } from '@fixtures/AcceptanceTest';

const hideProfilerToolbar = async (page: Parameters<typeof assertScreenshot>[0]) => {
    await hideElements(page, ['[id^="sfwdt"]', '.sf-toolbar', '.sf-minitoolbar']);
};

const prepareDetailScreenshot = async (
    page: Parameters<typeof assertScreenshot>[0],
    waitForSelector: Parameters<typeof setViewport>[1]['waitForSelector'],
) => {
    await setViewport(page, {
        width: 1440,
        waitForSelector,
        scrollableElementVertical: '.sw-cms-detail__stage',
    });
    await hideProfilerToolbar(page);
};

test('Visual: Shopping experiences pages', { tag: '@Visual' }, async ({
    ShopAdmin,
    AdminListingPageLayoutDetail,
    AdminApiContext,
    TestDataService,
    IdProvider,
}) => {
    test.slow();

    const createdLayoutId = IdProvider.getIdPair().uuid;

    await test.step('Create a deterministic listing layout via API.', async () => {
        await TestDataService.createBasicPageLayout('product_list', {
            id: createdLayoutId,
            name: 'test',
            type: 'product_list',
        });
    });

    await test.step('Open the detail page in the hidden-sidebar viewport and then resize.', async () => {
        await AdminListingPageLayoutDetail.page.setViewportSize({ width: 1400, height: 900 });
        await ShopAdmin.goesTo(AdminListingPageLayoutDetail.url(createdLayoutId));
        await ShopAdmin.expects(AdminListingPageLayoutDetail.saveButton).toBeVisible();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.loaderButton).not.toBeVisible();

        await prepareDetailScreenshot(
            AdminListingPageLayoutDetail.page,
            AdminListingPageLayoutDetail.saveButton,
        );
        await AdminListingPageLayoutDetail.settingsButton.click();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.sidebarTitle).toBeVisible();
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Settings-Tab.png');
    });

    await test.step('Capture the remaining sidebar states on the stable detail page.', async () => {
        await AdminListingPageLayoutDetail.blocksButton.click();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.loaderButton).not.toBeVisible();
        await prepareDetailScreenshot(
            AdminListingPageLayoutDetail.page,
            AdminListingPageLayoutDetail.saveButton,
        );
        await ShopAdmin.expects(AdminListingPageLayoutDetail.sidebarTitle).toBeVisible();
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Blocks-Tab.png');

        await AdminListingPageLayoutDetail.layoutAssignmentButton.click();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.loaderButton).not.toBeVisible();
        await prepareDetailScreenshot(
            AdminListingPageLayoutDetail.page,
            AdminListingPageLayoutDetail.saveButton,
        );
        await ShopAdmin.expects(AdminListingPageLayoutDetail.sidebarTitle).toBeVisible();
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Layout-Assignment-Tab.png');

        await AdminListingPageLayoutDetail.navigatorButton.click();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.loaderButton).not.toBeVisible();
        await ShopAdmin.expects(AdminListingPageLayoutDetail.saveButton).toBeVisible();
        await prepareDetailScreenshot(
            AdminListingPageLayoutDetail.page,
            AdminListingPageLayoutDetail.saveButton,
        );
        await ShopAdmin.expects(AdminListingPageLayoutDetail.sidebarTitle).toBeVisible();
        await assertScreenshot(AdminListingPageLayoutDetail.page, 'Layout-Detail-Navigator-Tab.png');
    });

    await test.step('Delete the created layout.', async () => {
        await AdminApiContext.delete(`cms-page/${createdLayoutId}`);
    });
});
