import { test } from '@fixtures/AcceptanceTest';

test('As an admin, I want to create documents and make sure they contain certain infos.', { tag: '@Documents' }, async ({
    ShopAdmin,
    TestDataService,
    DefaultSalesChannel,
    AdminDocumentListing,
    AdminDocumentDetail,
    AdminOrderDetail,
    ShopCustomer,
    StorefrontAccountOrder,
    Login,
    AddCreditItem,
    CreateInvoice,

    }) => {

    const product = await TestDataService.createBasicProduct();
    const order = await TestDataService.createOrder([{ product, quantity: 1 }], DefaultSalesChannel.customer);
    const orderId = order.id;

    await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
        await ShopAdmin.attemptsTo(AddCreditItem(orderId));
        await ShopAdmin.attemptsTo(CreateInvoice(orderId));
        await ShopAdmin.goesTo(AdminDocumentListing.url());
        await AdminDocumentListing.invoiceLink.click();
        await ShopAdmin.expects(AdminDocumentDetail.page.getByText('Document type Invoice')).toBeVisible();
        await AdminDocumentDetail.showInAccountSwitch.check();
        await AdminDocumentDetail.saveButton.click();
        await ShopAdmin.expects(AdminDocumentDetail.saveButton).not.toBeDisabled();
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
        await ShopAdmin.expects(AdminOrderDetail.page.getByTestId('mt-icon__regular-checkmark-xs')).toBeVisible();
        });

    await test.step('Log in to customer account and check the order document', async () => {
        await ShopCustomer.attemptsTo(Login());
        await ShopCustomer.goesTo(StorefrontAccountOrder.url());
        await ShopCustomer.expects(StorefrontAccountOrder.orderExpandButton).toBeVisible();
        await StorefrontAccountOrder.orderExpandButton.click();
        await ShopCustomer.expects(StorefrontAccountOrder.page.locator('.document-detail-content-header')).toBeVisible();
        await StorefrontAccountOrder.page.getByRole('link', { name: '.html' }).click();
        await ShopCustomer.expects(StorefrontAccountOrder.page.locator('.line-item:has-text("CreditItem")')).toContainText('-€1.00');
    });
});
