import { test } from '@fixtures/AcceptanceTest';

test('Shop administrator should be able to create a landing page.', {tag: '@Categories'}, async ({
    ShopAdmin,
    IdProvider,
    TestDataService,
    AdminCategories, CreateLandingPage, AdminLandingPageDetail, InstanceMeta,
}) => {

    test.skip(InstanceMeta.features['V6_7_0_0'], 'This test has a bug: https://shopware.atlassian.net/browse/NEXT-40153');

    const layoutUuid = IdProvider.getIdPair().uuid;
    const layoutName = `00_addlandingpage_${layoutUuid}`;
    const layoutType = 'landingpage';
    const landingPageData = {
        name: `00_addlandingpage_${IdProvider.getIdPair().uuid}`,
        salesChannel: 'Storefront',
        seoUrl: `00_addlandingpage_${IdProvider.getIdPair().id}`,
        status: true,
    };

    await test.step('Create a landing page layout via API.', async () => {

        await TestDataService.createBasicPageLayout(layoutType, {
                name: layoutName,
                id: layoutUuid,
                type: layoutType,
            },
        );
    });

    await test.step('Create a new landing page and assign layout.', async () => {
        await ShopAdmin.goesTo(AdminCategories.url());
        await ShopAdmin.attemptsTo(CreateLandingPage(layoutName, landingPageData));
    });

    await test.step('Verify a new landing page created and assigned layout.', async () => {
        const landingPage = await AdminCategories.getLandingPageByName(landingPageData.name);
        await landingPage.click();

        // Verify general tab detail
        await ShopAdmin.expects(AdminLandingPageDetail.nameInput).toHaveValue(landingPageData.name);
        await ShopAdmin.expects(AdminLandingPageDetail.landingPageStatus).toBeChecked({ checked: landingPageData.status });
        await ShopAdmin.expects(AdminLandingPageDetail.salesChannelSelectionList).toHaveText(landingPageData.salesChannel);
        await ShopAdmin.expects(AdminLandingPageDetail.seoUrlInput).toHaveValue(landingPageData.seoUrl);
        // Verify layout tab detail
        await AdminLandingPageDetail.layoutTab.click();
        await ShopAdmin.expects(AdminLandingPageDetail.layoutAssignmentCardTitle).toHaveText(layoutName);
        await ShopAdmin.expects(AdminLandingPageDetail.layoutAssignmentCardHeadline).toHaveText(layoutName);
        await ShopAdmin.expects(AdminLandingPageDetail.layoutAssignmentContentSection).toBeVisible();
        await ShopAdmin.expects(AdminLandingPageDetail.layoutResetButton).toBeVisible();
        await ShopAdmin.expects(AdminLandingPageDetail.changeLayoutButton).toBeVisible();
        await ShopAdmin.expects(AdminLandingPageDetail.editInDesignerButton).toBeVisible();
    });

});
