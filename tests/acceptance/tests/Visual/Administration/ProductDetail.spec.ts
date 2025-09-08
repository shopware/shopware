import { test, expect } from '@fixtures/AcceptanceTest';
import { setViewport, replaceElements, assertScreenshot } from '@shopware-ag/acceptance-test-suite';

test('Visual: Product Detail Page', { tag: '@Visual' }, async ({
    ShopAdmin,
    TestDataService,
    AdminProductDetail,
    }) => {

    const currency = await TestDataService.getCurrency('EUR');

    const product = await TestDataService.createBasicProduct({
        name: 'Test Product',
        productNumber: 'TEST-123',
        description: null,
        stock: 10,
        price: [
            {
                currencyId: currency.id,
                gross: 10,
                linked: false,
                net: 8.4,
            },
        ],
    });

    await test.step('Creates a screenshot of the product detail page General tab.', async () => {
        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/search/category',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-General-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Specifications tab.', async () => {
        await AdminProductDetail.specificationsTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/search-ids/property-group-option',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Specifications-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Advanced Pricing tab.', async () => {
        await AdminProductDetail.advancedPricingTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Advanced-Pricing-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Variants tab.', async () => {
        await AdminProductDetail.variantsTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/search/product',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Variants-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Layout tab.', async () => {
        await AdminProductDetail.layoutTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Layout-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page SEO tab.', async () => {
        await AdminProductDetail.SEOTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/search/sales-channel',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-SEO-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Cross Selling tab.', async () => {
        await AdminProductDetail.crossSellingTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Cross-Selling-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Reviews tab.', async () => {
        await AdminProductDetail.reviewsTabLink.click();
        await setViewport(AdminProductDetail.page, {
            requestURL: 'api/app-system/action-button/product/detail',
        });
        await replaceElements(AdminProductDetail.page, [
            AdminProductDetail.productHeadline,
        ]);
        await assertScreenshot(AdminProductDetail.page, 'Product-Detail-Reviews-Tab.png');
    });
});
