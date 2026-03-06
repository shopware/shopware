import {test, formatPrice} from '@fixtures/AcceptanceTest';
import { screenshotPdfPopup, DocumentTypes } from '@helpers/document-helpers';

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
        let invoiceDocumentId: string;

        const product = await TestDataService.createBasicProduct();

        const order = await TestDataService.createOrder(
            [{ product, quantity: 1 }],
            DefaultSalesChannel.customer
        );

        await test.step('Go to documents settings page and activate documents in customer accounts', async () => {
            await ShopAdmin.goesTo(AdminDocumentListing.url());

            await AdminDocumentListing.invoiceLink.click();
            await ShopAdmin.expects(AdminDocumentDetail.documentTypeSelect).toContainText('Invoice');

            await AdminDocumentDetail.showInAccountSwitch.check();
            await AdminDocumentDetail.saveButton.click();
        });

        await test.step('Create documents and verify pdf', async () => {
            const documents = [
                'invoice',
                'credit_note',
                'delivery_note',
                'cancellation_invoice',
                'embedded_zugferd_e_invoice',
            ];

            for (const type of documents as DocumentTypes[]) {
                if (type === 'credit_note') {
                    await ShopAdmin.attemptsTo(AddCreditItem(order.id));
                }

                const { documentId } = await CreateDocument({
                    orderId: order.id,
                    type,
                    referencedDocumentId: invoiceDocumentId,
                })();

                if (type === 'invoice') {
                    invoiceDocumentId = documentId;
                }

                await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

                const firstDocumentRow = AdminOrderDetail.getDocumentRow(0);

                await firstDocumentRow.contextMenuButton.click();
                await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();

                await screenshotPdfPopup(
                    AdminOrderDetail.contextMenuOpenDocument,
                    ShopAdmin.expects,
                    type,
                );
            }
        });

        await test.step('Go to documents tab and send invoice', async () => {
            await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

            const lastDocumentRow = AdminOrderDetail.getDocumentRow(4);

            await ShopAdmin.expects(lastDocumentRow.row).toBeVisible();
            await lastDocumentRow.contextMenuButton.click();

            await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();
            await AdminOrderDetail.contextMenuMarkAsSent.click();

            await ShopAdmin.expects(AdminOrderDetail.contextMenu).not.toBeVisible();
            await ShopAdmin.expects(lastDocumentRow.sentCheckmark).toBeVisible();
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
