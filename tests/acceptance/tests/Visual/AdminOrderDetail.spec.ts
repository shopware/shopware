import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Order Detail Page', { tag: '@Visual' }, async ({ ShopAdmin, TestDataService, AdminOrderDetail, HideElementsForScreenshot }) => {

    await test.step('Creates a screenshot of the order detail page General tab.', async () => {

        const product = await TestDataService.createBasicProduct({
            name: 'Test Product',
            productNumber: 'TEST-123',
            description: null,
            stock: 10,
            price: [{
                currencyId: (await TestDataService.getCurrency('EUR')).id,
                gross: 100,
                linked: false,
                net: 84,
            }],
        });
        const customer = await TestDataService.createCustomer({
            firstName: 'John',
            lastName: 'Doe',
            email: 'john@doe.com',
            customerNumber: 'CUST-123',
            billingAddress: {
                firstName: 'John',
                lastName: 'Doe',
                street: 'Test Street 1',
                zipcode: '12345',
                city: 'Test City',
            },
        });   
        const order = await TestDataService.createOrder(
            [{ product: product, quantity: 1 }],
            customer
        );

        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
        await AdminOrderDetail.page.setViewportSize({ width: 1440, height: 1600 }); 

        await HideElementsForScreenshot(AdminOrderDetail.page, [
            '.sw-order-general-info__summary-sub',
            '.sw-order-general-info__summary-main-header',
            '.smart-bar__header',
        ]);

        await expect(AdminOrderDetail.page.locator('.sw-desktop__content')).toHaveScreenshot(); 
    });


    await test.step('Creates a screenshot of the product detail page Details tab.', async () => { 
        await AdminOrderDetail.detailsTabLink.click();
        await ShopAdmin.expects((await AdminOrderDetail.page.waitForResponse(response=>response.url().includes('/api/search/custom-field-set'))).ok()).toBeTruthy();
        await AdminOrderDetail.page.setViewportSize({ width: 1440, height: 2400 });

        await HideElementsForScreenshot(AdminOrderDetail.page,[
            '.sw-order-general-info__summary-sub',
            '.sw-single-select__selection-text',
            '.dp__input_reg',  
        ]);

        await expect(AdminOrderDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });

        await test.step('Creates a screenshot of the product detail page Documents tab.', async () => { 
        await AdminOrderDetail.documentsTabLink.click();
        await ShopAdmin.expects((await AdminOrderDetail.page.waitForResponse(response=>response.url().includes('/api/search/document'))).ok()).toBeTruthy();
        await AdminOrderDetail.page.setViewportSize({ width: 1440, height: 800 });
        await expect(AdminOrderDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });
});
