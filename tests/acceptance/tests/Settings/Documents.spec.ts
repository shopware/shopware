import {expect, test} from '@fixtures/AcceptanceTest';

test('As an admin, I want to create documents and make sure they contain certain infos.', { tag: '@Documents' }, async ({
    ShopAdmin,
    TestDataService,
    DefaultSalesChannel,
    AdminDocumentListing,
    AdminDocumentDetail,
    AdminOrderDetail,
    ShopCustomer,
    StorefrontAccountOrder,
    StorefrontAccountLogin,
    Login,
    AdminApiContext,

    }) => {

    const product = await TestDataService.createBasicProduct();
    const order = await TestDataService.createOrder([{ product, quantity: 1 }], DefaultSalesChannel.customer);
    const creditItem = {
        'identifier': order.id,
        'orderId': order.id,
        'quantity': 1,
        'label': 'CreditItem',
        'payload': [],
        'good': true,
        'removable': true,
        'stackable': true,
        'position': 2,
        'states': [],
        'price': {
            'unitPrice': -1.0,
            'totalPrice': -1.0,
            'calculatedTaxes': [
                {
                    'extensions': [],
                    'tax': -0.16,
                    'taxRate': 19.0,
                    'price': -1.0,

                },
            ],
            'taxRules': [
                {
                    'extensions': [],
                    'taxRate': 19.0,
                    'percentage': 100.0,
                },
            ],
            'quantity': 1,
        },
    }
    const productResponse = await AdminApiContext.post('order-line-item', {
        data: creditItem,
    });
    ShopAdmin.expects(productResponse.ok()).toBeTruthy();

    const orderInvoice = {
        'data':
        {
            'orderId': order.id,
            'config': {
                'name': 'invoice',
            },
        },
    }
    const orderResponse = await AdminApiContext.post('_action/order/document/invoice/create', {
        data: orderInvoice,
    });
    ShopAdmin.expects(orderResponse.ok()).toBeTruthy();

    await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
        await ShopAdmin.goesTo(AdminDocumentListing.url());
        await AdminDocumentListing.invoiceLink.click();
        await ShopAdmin.expects(AdminDocumentDetail.page.getByText('Document type Invoice')).toBeVisible();
        await AdminDocumentDetail.showInAccountSwitch.click();
        await AdminDocumentDetail.saveButton.click();
        });
    await test.step('Go to order detail page and check for credit item', async () => {
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'general'));
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-data-grid__row--1')).toContainText('CreditItem');
        });
    await test.step('Go to documents tab and send invoice', async () => {
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));
        await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-data-grid__table')).toContainText('Invoice');
        await AdminOrderDetail.page.getByLabel('Open actions menu').click();
        await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-context-menu')).toBeVisible();
        await AdminOrderDetail.page.locator('.sw-context-menu').getByText('Send document').click();
        await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-order-send-document-modal')).toBeVisible();
        await AdminOrderDetail.page.getByRole('button').getByText('Send document').click();
        });
    await test.step('Go to customer account in Storefront and check the order document', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());


    });
});
