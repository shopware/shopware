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

        await test.step('Create documents and verify PDF screenshots', async () => {
            for (const type of ['invoice', 'cancellation_invoice'] as DocumentTypes[]) {
                const { documentId } = await CreateDocument({
                    orderId: order.id,
                    type,
                    referencedDocumentId: invoiceDocumentId,
                })();

                // eslint-disable-next-line playwright/no-conditional-in-test
                if (type === 'invoice') {
                    invoiceDocumentId = documentId;
                }

                await ShopAdmin.goesTo(AdminOrderDetail.url(order.id, 'documents'));

                const rowLocator = AdminOrderDetail.page.locator('.sw-data-grid__body .sw-data-grid__row').nth(0);

                await rowLocator.getByLabel('Open actions menu').click();
                await ShopAdmin.expects(AdminOrderDetail.contextMenu).toBeVisible();

                await screenshotPdfPopup(
                    AdminOrderDetail.page.locator('.sw-context-menu').getByText('Open document'),
                    ShopAdmin.expects,
                    type,
                );
            }
        });

        await test.step.skip('Log into customer account and check the order document', async () => {
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
