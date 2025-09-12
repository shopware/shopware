import { test, setViewport, hideElements, replaceElements, assertScreenshot } from '@fixtures/AcceptanceTest';

test('Visual: Order Detail Page', { tag: '@Visual' }, async ({
    ShopAdmin,
    TestDataService,
    AdminOrderDetail,
    DefaultSalesChannel,
}) => {

    await test.step('Creates a screenshot of the order detail page General tab.', async () => {

        const product = await TestDataService.createBasicProduct();
        const order = await TestDataService.createOrder([{ product, quantity: 1 }], DefaultSalesChannel.customer);
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id));

        await setViewport(AdminOrderDetail.page, {
            requestURL: 'api/search/user-config',
        });

        await replaceElements(AdminOrderDetail.page, [
            'td.sw-data-grid__cell--label .sw-order-line-items-grid__item-label',
            '.sw-order-general-info__summary-main-header',
            '.sw-order-general-info__summary-sub',
            '.smart-bar__header',
        ]);

        await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-General-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Details tab.', async () => {
        await AdminOrderDetail.detailsTabLink.click();
        await setViewport(AdminOrderDetail.page, {
            requestURL: '/api/search/custom-field-set',
        });

        await replaceElements(AdminOrderDetail.page, [
            'input[placeholder="Enter email address..."]',
            '.sw-order-general-info__summary-sub',
            'div.sw-field[label="Billing address"] .sw-single-select__selection-text',
            'div.sw-field[label="Shipping address"] .sw-single-select__selection-text',
        ]);

        await hideElements(AdminOrderDetail.page,[
            '.dp__input_reg',
            'input[aria-label="Email"]',
            'div.sw-field[label="Sales channel"] .sw-entity-single-select__selection-text',
        ]);

        await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-Details-Tab.png');
    });

    await test.step('Creates a screenshot of the product detail page Documents tab.', async () => {
        await AdminOrderDetail.documentsTabLink.click();
        await setViewport(AdminOrderDetail.page, {
            requestURL: '/api/search/document',
            contentHeight: 1080,
        });
        await assertScreenshot(AdminOrderDetail.page, 'Order-Detail-Documents-Tab.png');
    });
});
