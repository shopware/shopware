import { test, formatPrice } from '@fixtures/AcceptanceTest';

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
        CreateDocument,
    }) => {
        const product = await TestDataService.createBasicProduct();

        const order = await TestDataService.createOrder(
            [{ product, quantity: 1 }],
            DefaultSalesChannel.customer
        );

        await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
            await ShopAdmin.goesTo(AdminDocumentListing.url());

            await AdminDocumentListing.invoiceLink.click();
            await ShopAdmin.expects(AdminDocumentDetail.documentTypeSelect).toContainText('Invoice');

            await AdminDocumentDetail.displayDocumentInMyAccountSwitch.check();
            await AdminDocumentDetail.saveButton.click();

            await ShopAdmin.attemptsTo(AddCreditItem(order.id));
            await CreateDocument({
                orderId: order.id,
                type: 'invoice',
            })();
        });

        await test.step('Go to documents tab and send invoice', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

            const documentRow = AdminOrderDetail.getDocumentRow(0);

            await ShopAdmin.expects(documentRow.row).toBeVisible();
            await documentRow.contextMenuButton.click();

            await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();
            await AdminOrderDetail.contextMenuMarkAsSent.click();

            await ShopAdmin.expects(AdminOrderDetail.contextMenu).not.toBeVisible();
            await ShopAdmin.expects(documentRow.sentCheckmark).toBeVisible();
        });

        await test.step('Log into customer account and check the order document', async () => {
            await ShopCustomer.attemptsTo(Login());
            await ShopCustomer.goesTo(StorefrontAccountOrder.url());

            await ShopCustomer.expects(StorefrontAccountOrder.orderExpandButton).toBeVisible();
            await StorefrontAccountOrder.orderExpandButton.click();

            await ShopCustomer.expects(StorefrontAccountOrder.orderDetails).toBeVisible();
            await StorefrontAccountOrder.invoiceHTML.click();
            await ShopCustomer.expects(StorefrontAccountOrder.creditItem).toContainText(formatPrice(1.0));
        });
    }
);
