import { test as base } from '@playwright/test';
import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import type { DocumentTypes, DocumentOptions } from '@helpers/document-helpers';

// eslint-disable-next-line no-unused-vars
type CreateDocumentTask = (options: DocumentOptions) => () => Promise<{ documentId: string }>;

const documentEndpoints: Record<DocumentTypes, string> = {
    invoice: '_action/order/document/invoice/create',
    credit_note: '_action/order/document/credit_note/create',
    delivery_note: '_action/order/document/delivery_note/create',
    cancellation_invoice: '_action/order/document/storno/create',
    zugferd_embedded_invoice: '_action/order/document/zugferd_embedded_invoice/create',
    zugferd_embedded_cancellation_invoice: '_action/order/document/zugferd_embedded_cancellation_invoice/create',
    zugferd_embedded_credit_note: '_action/order/document/zugferd_embedded_credit_note/create',
};

const typesRequiringInvoice: DocumentTypes[] = ['credit_note', 'cancellation_invoice'];

export const CreateDocument = base.extend<{ CreateDocument: CreateDocumentTask }, FixtureTypes>({
    CreateDocument: async ({ AdminApiContext, ShopAdmin }, use) => {
        const task = (options: DocumentOptions) => {
            return async function CreateDocument() {
                const { orderId, type, referencedDocumentId } = options;

                const documentPayload: Record<string, unknown>[] = [{
                    orderId: orderId,
                }];

                if (typesRequiringInvoice.includes(type) && referencedDocumentId) {
                    documentPayload[0].referencedDocumentId = referencedDocumentId;
                }

                ShopAdmin.expects(documentEndpoints).toHaveProperty(type);

                const endpoint = documentEndpoints[type];
                const response = await AdminApiContext.post(endpoint, {
                    data: documentPayload,
                });

                ShopAdmin.expects(response.ok()).toBeTruthy();

                const responseData = await response.json();
                ShopAdmin.expects(responseData).toHaveProperty('data');

                const data = responseData.data;
                ShopAdmin.expects(data.length).toBeGreaterThan(0);
                ShopAdmin.expects(data[0]).toHaveProperty('documentId');

                return { documentId: data[0].documentId };
            };
        };

        await use(task);
    },
});
