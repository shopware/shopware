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
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });       
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot(); 
    });
    
    await test.step('Creates a screenshot of the product detail page Specifications tab.', async () => { 
        await AdminProductDetail.specificationsTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Advanced Pricing tab.', async () => { 
        await AdminProductDetail.advancedPricingTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Variants tab.', async () => { 
        await AdminProductDetail.variantsTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Layout tab.', async () => { 
        await AdminProductDetail.layoutTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page SEO tab.', async () => { 
        await AdminProductDetail.SEOTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Cross Selling tab.', async () => { 
        await AdminProductDetail.crossSellingTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

    await test.step('Creates a screenshot of the product detail page Reviews tab.', async () => { 
        await AdminProductDetail.reviewsTabLink.click();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 2240 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

});