import { test, setViewport, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Visual: Promotions Listing Page', { tag: '@Visual' }, async ({ 
    ShopAdmin, 
    TestDataService, 
    AdminPromotionsListing,
 }) => {
    await test.step('Creates a screenshot of the promotions listing page in its empty state.', async () => { 
        await ShopAdmin.goesTo(AdminPromotionsListing.url());
        await setViewport(AdminPromotionsListing.page, {
            waitForSelector: AdminPromotionsListing.smartBarAddPromotionButton,
            scrollableElementVertical: AdminPromotionsListing.page.locator('.sw-page__main-content'),
        });
        await assertScreenshot(AdminPromotionsListing.page, 'Listing-Empty-State.png');
    });

    await test.step('Creates a screenshot of the promotions listing page with an active and an inactive promotion.', async () => {
        await TestDataService.createPromotionWithCode({ name: 'TestPromotion-Active', active: true });
        await TestDataService.createPromotionWithCode({ name: 'TestPromotion-Inactive' ,active: false });
        
        await ShopAdmin.goesTo(AdminPromotionsListing.url());
        await setViewport(AdminPromotionsListing.page, {
            waitForSelector: AdminPromotionsListing.smartBarAddPromotionButton,
            scrollableElementVertical: AdminPromotionsListing.page.locator('.sw-page__main-content'),
        }); 
        await assertScreenshot(AdminPromotionsListing.page, 'Listing-With-Promotions.png');
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
            waitForSelector: AdminPromotionCreate.saveButton,
        });
        await assertScreenshot(AdminPromotionCreate.page, 'Create.png');
    });

    const testPromo = await TestDataService.createPromotionWithCode({ name: 'TestPromo', code: '123' });
    await test.step('Creates a screenshot of the promotions detail page: General Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id));
        await setViewport(AdminPromotionDetail.page, {
            waitForSelector: AdminPromotionCreate.saveButton,
        });
        await assertScreenshot(AdminPromotionDetail.page, 'Detail-General-Tab.png');
    });

    await test.step('Creates a screenshot of the promotions detail page: Conditions Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id, 'conditions'));
        await setViewport(AdminPromotionDetail.page, {
            waitForSelector: AdminPromotionCreate.saveButton,
        });
        await assertScreenshot(AdminPromotionDetail.page, 'Detail-Conditions-Tab.png');
    });

    await test.step('Creates a screenshot of the promotions detail page: Discounts Tab.', async () => {
        await ShopAdmin.goesTo(AdminPromotionDetail.url(testPromo.id, 'discounts'));
        await setViewport(AdminPromotionDetail.page, {
            waitForSelector: AdminPromotionCreate.saveButton,
        });
        await assertScreenshot(AdminPromotionDetail.page, 'Detail-Discounts-Tab.png');
    });

    await test.step('Creates a screenshot of the promotions detail page: Discounts Tab - With additional Discount.', async () => {
        await AdminPromotionDetail.addDiscountButton.click();
        await setViewport(AdminPromotionDetail.page, {
            waitForSelector: AdminPromotionCreate.saveButton,
        });
        await assertScreenshot(AdminPromotionDetail.page, 'Detail-Discounts-Tab-Additional-Discount.png');
    });
});