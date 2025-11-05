import { test, getLocale, getCurrencySymbolFromLocale } from '@fixtures/AcceptanceTest';

test(
    'As an admin, I want to create documents and make sure they contain certain infos.',
    { tag: '@Documents' },
    async ({
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
        const currencyIcon = getCurrencySymbolFromLocale(getLocale());
        await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
            await ShopAdmin.goesTo(AdminDocumentListing.url());
            await AdminDocumentListing.invoiceLink.click();
            await ShopAdmin.expects(AdminDocumentDetail.documentTypeSelect).toContainText('Invoice');
            await AdminDocumentDetail.showInAccountSwitch.check();
            await AdminDocumentDetail.saveButton.click();
            await ShopAdmin.expects(AdminDocumentDetail.saveButton).not.toBeDisabled();
            await ShopAdmin.attemptsTo(AddCreditItem(orderId));
            await ShopAdmin.attemptsTo(CreateInvoice(orderId));
        });

        await test.step('Go to order detail page and check for credit item', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'general'));
            await ShopAdmin.expects(AdminOrderDetail.lineItemsTable).toContainText('CreditItem');
        });

        await test.step('Go to documents tab and send invoice', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(orderId, 'documents'));
            await ShopAdmin.expects(AdminOrderDetail.documentType).toContainText('Invoice');
            await AdminOrderDetail.contextMenuButton.click();
            await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();
            await AdminOrderDetail.page.locator('.sw-context-menu').getByText('Mark as sent').click();
            await ShopAdmin.expects(AdminOrderDetail.contextMenu).not.toBeVisible();
            await ShopAdmin.expects(AdminOrderDetail.sentCheckmark).toBeVisible();
        });

        await test.step('Log into customer account and check the order document', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());
            await ShopCustomer.expects(StorefrontAccountOrder.orderExpandButton).toBeVisible();
            await StorefrontAccountOrder.orderExpandButton.click();
            await ShopCustomer.expects(StorefrontAccountOrder.orderDetails).toBeVisible();
            await StorefrontAccountOrder.invoiceHTML.click();
            await ShopCustomer.expects(StorefrontAccountOrder.creditItem).toContainText(`-${currencyIcon}1.00`);
        });
    }
);
