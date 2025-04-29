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
            'extensions': [],
            'unitPrice': -1.0,
            'totalPrice': -1.0,
            'calculatedTaxes': [
                {
                    'extensions': [],
                    'tax': -0.16,
                    'taxRate': 19.0,
                    'price': -1.0,
                    'label': null,
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
            'referencePrice': null,
            'listPrice': null,
            'regulationPrice': null,
        },
        'priceDefinition': {
            'extensions': [],
            'price': -1.0,
            'filter': {
                '_name': 'cartLineItemOfType',
                'operator': '!=',
                'lineItemType': 'credit',
            },
            'type': 'absolute',
        },
        'unitPrice': -1.0,
        'totalPrice': -1.0,
        'description': 'credit line item',
        'type': 'credit',
        'customFields': null,
        'apiAlias': 'order_line_item_foreign_keys_extension',
    }
    const ruleResponse = await AdminApiContext.post('order-line-item', {
        data: creditItem,
    });
    ShopAdmin.expects(ruleResponse.ok()).toBeTruthy();

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
    await test.step('Go to documents tab and create invoice', async () => {
        await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));
        await ShopAdmin.expects(AdminOrderDetail.page.locator('.sw-empty-state')).toContainText('No documents created yet');
        await AdminOrderDetail.page.getByRole('button', { name: 'Create new document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__body')).toContainText('Invoice');
        await AdminDocumentDetail.page.locator('.sw-modal__body').getByRole('radio', { name: 'Invoice', exact: true }).click();
        await AdminDocumentDetail.page.locator('.sw-modal__footer').getByRole('button', { name: 'Create document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__header')).toHaveText('Create document - Invoice');
        //await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-context-button__menu-position')).toBeVisible();
        await AdminDocumentDetail.page.getByTestId('mt-icon__regular-chevron-down-xs').nth(1).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-context-menu')).toBeVisible();
        await AdminDocumentDetail.page.locator('.sw-context-menu-item__text').getByText('Create and send').click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.mt-button__loader')).not.toBeVisible();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.sw-modal__header')).toHaveText('Send document');
        await AdminDocumentDetail.page.locator('.sw-modal__footer').getByRole('button', { name: 'Send document' }).click();
        await ShopAdmin.expects(AdminDocumentDetail.page.locator('.mt-button__loader')).not.toBeVisible();
        });
    await test.step('Go to customer account in Storefront and check the order document', async () => {
        await ShopCustomer.goesTo(StorefrontAccountLogin.url());
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());


    });
});
