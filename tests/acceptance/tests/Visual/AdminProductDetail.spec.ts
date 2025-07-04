import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Product Detail Page', { tag: '@Visual' }, async ({ ShopAdmin, TestDataService, AdminProductDetail }) => {

    await test.step('Creates a screenshot of the product detail page General tab.', async () => {
        const currency = await TestDataService.getCurrency('EUR');
        
        const product = await TestDataService.createBasicProduct({
            name: 'Test Product',
            productNumber: 'TEST-123',
            description: null,
            stock:10,
            price: [
                {
                    currencyId: currency.id,
                    gross: 10,
                    linked: false,
                    net: 8.4,
                },
            ],
        });

        await ShopAdmin.goesTo(AdminProductDetail.url(product.id));
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 4200 }); 
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot(); 
    });
    
    await test.step('Creates a screenshot of the product detail page Specifications tab.', async () => { 
        await AdminProductDetail.specificationsTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/app-system/action-button/product/detail'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 1700 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Advanced Pricing tab.', async () => { 
        await AdminProductDetail.advancedPricingTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/search/rule'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 640 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Variants tab.', async () => { 
        await AdminProductDetail.variantsTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/search/property-group'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 860 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Layout tab.', async () => { 
        await AdminProductDetail.layoutTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/search/custom-field'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 920 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page SEO tab.', async () => { 
        await AdminProductDetail.SEOTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/search/sales-channel'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 1200 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Cross Selling tab.', async () => { 
        await AdminProductDetail.crossSellingTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/app-system/action-button/product/detail'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 860 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Reviews tab.', async () => { 
        await AdminProductDetail.reviewsTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/search/product-review'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 860 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });
});
