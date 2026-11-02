import {
    createDocument,
    createOrder,
    mountDocumentCard,
    setUpDocuments,
    unmountDocumentCards,
} from './document-card.harness';

/**
 * @sw-package after-sales
 */

const creditLineItem = { id: 'creditId', type: 'credit' };
const creditNote = { technicalName: 'credit_note' };
const cancellationInvoice = { technicalName: 'storno' };

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    it('lists the credit line items of the order', async () => {
        // ARRANGE
        const wrapper = await mountDocumentCard({
            order: createOrder({ lineItems: [creditLineItem, { id: 'productId', type: 'product' }] }),
        });

        // ACT
        const creditItems: unknown = wrapper.vm.creditItems;

        // ASSERT
        expect(creditItems).toEqual([creditLineItem]);
    });

    it('offers the asset filter', async () => {
        // ARRANGE
        const wrapper = await mountDocumentCard();

        // ACT
        const assetFilter: unknown = wrapper.vm.assetFilter;

        // ASSERT
        expect(assetFilter).toEqual(expect.any(Function));
    });

    /**
     * @deprecated tag:v6.8.0 - dateFilter is removed
     */
    it.deprecated('v6.8.0.0')('offers the date filter', async () => {
        // ARRANGE
        const wrapper = await mountDocumentCard();

        // ACT
        const dateFilter: unknown = wrapper.vm.dateFilter;

        // ASSERT
        expect(dateFilter).toEqual(expect.any(Function));
    });

    /**
     * @deprecated tag:v6.8.0 - invoiceExists is removed
     */
    it.deprecated('v6.8.0.0')('tells whether the order has an invoice', async () => {
        // ARRANGE
        setUpDocuments([createDocument('invoiceA')]);
        const withInvoice = await mountDocumentCard();
        setUpDocuments([]);
        const withoutInvoice = await mountDocumentCard();

        // ACT
        const answers: unknown[] = [withInvoice.vm.invoiceExists(), withoutInvoice.vm.invoiceExists()];

        // ASSERT
        expect(answers).toEqual([true, false]);
    });

    /**
     * @deprecated tag:v6.8.0 - documentTypeAvailable is removed
     */
    it.deprecated('v6.8.0.0')('offers credit notes and cancellations only with an invoice', async () => {
        // ARRANGE
        setUpDocuments([createDocument('invoiceA')]);
        const withInvoice = await mountDocumentCard({ order: createOrder({ lineItems: [creditLineItem] }) });
        setUpDocuments([]);
        const withoutInvoice = await mountDocumentCard({ order: createOrder({ lineItems: [creditLineItem] }) });

        // ACT
        const answers: unknown[] = [
            withInvoice.vm.documentTypeAvailable({ technicalName: 'invoice' }),
            withInvoice.vm.documentTypeAvailable(creditNote),
            withInvoice.vm.documentTypeAvailable(cancellationInvoice),
            withoutInvoice.vm.documentTypeAvailable(creditNote),
            withoutInvoice.vm.documentTypeAvailable(cancellationInvoice),
        ];

        // ASSERT
        expect(answers).toEqual([
            true,
            true,
            true,
            false,
            false,
        ]);
    });

    /**
     * @deprecated tag:v6.8.0 - documentTypeAvailable is removed
     */
    it.deprecated('v6.8.0.0')('offers no credit note for an order without credit line items', async () => {
        // ARRANGE
        setUpDocuments([createDocument('invoiceA')]);
        const wrapper = await mountDocumentCard();

        // ACT
        const available: unknown = wrapper.vm.documentTypeAvailable(creditNote);

        // ASSERT
        expect(available).toBe(false);
    });
});
