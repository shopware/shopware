import { test } from '@fixtures/AcceptanceTest';
import { screenshotDocument, DocumentTypes } from '@helpers/document-helpers';

test(
    'Visual: Document PDFs should match expected appearance',
    { tag: '@Visual @Documents' },
    async ({
        ShopAdmin,
        TestDataService,
        DefaultSalesChannel,
        AdminOrderDetail,
        AddCreditItem,
        CreateDocument,
    }) => {
        let invoiceDocumentId: string;

        const product = await TestDataService.createBasicProduct();

        const order = await TestDataService.createOrder(
            [{ product, quantity: 1 }],
            DefaultSalesChannel.customer
        );

        const documents: DocumentTypes[] = [
            'invoice',
            'credit_note',
            'delivery_note',
            'cancellation_invoice',
            'embedded_zugferd_e_invoice',
        ];

        for (const type of documents) {
            await test.step(`Verify ${type} pdf`, async () => {
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

                await screenshotDocument(
                    AdminOrderDetail.contextMenuOpenDocument,
                    ShopAdmin.expects,
                    type,
                );
            });
        }
    }
);
