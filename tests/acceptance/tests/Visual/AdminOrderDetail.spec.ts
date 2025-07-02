import { test, expect } from '@fixtures/AcceptanceTest';

test('Visual: Order Detail Page', { tag: '@Visual' }, async ({ ShopAdmin, TestDataService, AdminOrderDetail }) => {

    await test.step('Creates a screenshot of the order detail page General tab.', async () => {
        /*
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
        });     
        
        const order = await TestDataService.createOrder({
            lineItems: [{ product: product, quantity: 2 }],
            customer,
            orderNumber: 'ORDER-123'        });
            */

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
        });   
        const order = await TestDataService.createOrder(
            [{ product: product, quantity: 1 }],
            customer
        );


        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));
        await AdminOrderDetail.page.setViewportSize({ width: 1440, height: 1600 }); 
        await AdminOrderDetail.page.addStyleTag({
            content: `
                .sw-order-general-info__summary-sub,
                .sw-order-general-info__summary-main-header,
                .smart-bar__header
                {display: none !important;}`,
        });

        await expect(AdminOrderDetail.page.locator('.sw-desktop__content')).toHaveScreenshot(); 
    });


        await test.step('Creates a screenshot of the product detail page Details tab.', async () => { 
        await AdminProductDetail.specificationsTabLink.click();
        await ShopAdmin.expects((await AdminProductDetail.page.waitForResponse(response=>response.url().includes('/api/app-system/action-button/product/detail'))).ok()).toBeTruthy();
        await AdminProductDetail.page.setViewportSize({ width: 1440, height: 1700 });
        await expect(AdminProductDetail.page.locator('.sw-desktop__content')).toHaveScreenshot();  
    });
});
