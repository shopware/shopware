import { test, expect } from '@fixtures/AcceptanceTest';
test('Visual: Product Detail Page', { tag: '@Visual' }, async ({
    ShopAdmin,
    TestDataService,
    AdminProductDetail,
    ReplaceElementsForScreenshot,
    }) => {

    // const currency = await TestDataService.getCurrency('EUR');

    const product = await TestDataService.createBasicProduct({
        // name: 'Test Product',
        // productNumber: 'TEST-123',
        // description: null,
        // stock: 10,
        // price: [
        //     {
        //         currencyId: currency.id,
        //         gross: 10,
        //         linked: false,
        //         net: 8.4,
        //     },
        // ],
    });

    await test.step('Creates a screenshot of the product detail page General tab.', async () => {
        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/search/category',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
            '.sw-field--product-name',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-General-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Specifications tab.', async () => {
        await AdminProductDetail.specificationsTabLink.click();
          const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/search/property-group-option',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
            '.sw-field--product-name',
            '.mt-field',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Specifications-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Advanced Pricing tab.', async () => {
        await AdminProductDetail.advancedPricingTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Advanced-Pricing-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Variants tab.', async () => {
        await AdminProductDetail.variantsTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/search/product',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Variants-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Layout tab.', async () => {
        await AdminProductDetail.layoutTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Layout-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page SEO tab.', async () => {
        await AdminProductDetail.SEOTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/search/sales-channel',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-SEO-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Cross Selling tab.', async () => {
        await AdminProductDetail.crossSellingTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Cross-Selling-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Reviews tab.', async () => {
        await AdminProductDetail.reviewsTabLink.click();
        const viewportSize = await AdminProductDetail.getViewportDimensions({
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await AdminProductDetail.page.setViewportSize({ width: viewportSize.contentWidth, height: viewportSize.totalHeight });
        await ReplaceElementsForScreenshot(AdminProductDetail.page, [
            '.smart-bar__header',
        ]);
        await ShopAdmin.expects(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Product-Detail-Reviews-Tab.png');
    });
});
