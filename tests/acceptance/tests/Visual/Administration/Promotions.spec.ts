import { test, expect, setViewport, replaceElements } from '@fixtures/AcceptanceTest';

test('Visual: Promotions Listing Page', { tag: '@Visual' }, async ({ 
    ShopAdmin, 
    TestDataService, 
    AdminPromotionsListing,
 }) => {
    await test.step('Creates a screenshot of the promotions listing page in its empty state.', async () => { 
        await ShopAdmin.goesTo(AdminPromotionsListing.url());
        await setViewport(AdminPromotionsListing.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminPromotionsListing.page.locator('.sw-desktop__content')).toHaveScreenshot('Listing-Empty-State.png');
    });

    await test.step('Creates a screenshot of the promotions listing page with an active and an inactive promotion.', async () => {
        await TestDataService.createPromotionWithCode({ active: true });
        await TestDataService.createPromotionWithCode({ active: false });
        
        await ShopAdmin.goesTo(AdminPromotionsListing.url());
        await replaceElements(AdminPromotionsListing.page, [ '.sw-data-grid__cell--name' ]);
        await setViewport(AdminPromotionsListing.page, {
            width: 1440,
            contentHeight: 1200,
        }); 
        await expect(AdminPromotionsListing.page.locator('.sw-desktop__content')).toHaveScreenshot('Listing-With-Promotions.png'); 
    });
});

test('Visual: Promotion Detail Page', { tag: '@Visual' }, async ({ 
    ShopAdmin, 
    TestDataService, 
    AdminPromotionCreate,
    AdminPromotionDetail,
 }) => {

    await test.step('Creates a screenshot of the promotion create page.', async () => { 
        await ShopAdmin.goesTo(AdminPromotionCreate.url());
        await setViewport(AdminPromotionCreate.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminPromotionCreate.page.locator('.sw-desktop__content')).toHaveScreenshot('Create.png');
    });

    const testPromo = await TestDataService.createPromotionWithCode({ name: 'TestPromo', code: '123' });
    await test.step('Creates a screenshot of the promotions detail page: General Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id));
        await setViewport(AdminPromotionDetail.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminPromotionDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Detail-General-Tab.png'); 
    });

    await test.step('Creates a screenshot of the promotions detail page: Conditions Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id, 'conditions'));
        await setViewport(AdminPromotionDetail.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminPromotionDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Detail-Conditions-Tab.png'); 
    });

    await test.step('Creates a screenshot of the promotions detail page: Discounts Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id, 'discounts'));
        await setViewport(AdminPromotionDetail.page, {
            width: 1440,
            contentHeight: 1200,
        });
        await expect(AdminPromotionDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Detail-Discounts-Tab.png'); 
    });

    await test.step('Creates a screenshot of the promotions detail page: Discounts Tab - With additional Discount.', async () => {
        await AdminPromotionDetail.addDiscountButton.click();
        await setViewport(AdminPromotionDetail.page, {
            width: 1440,
            contentHeight: 1800,
        });
        await expect(AdminPromotionDetail.page.locator('.sw-desktop__content')).toHaveScreenshot('Detail-Discounts-Tab-Additional-Discount.png'); 
    });
});