import {
    createDocument,
    createOrder,
    getDocumentSearches,
    getShownDocumentNumbers,
    holdNextDocumentSearch,
    mountDocumentCard,
    setUpDocuments,
    unmountDocumentCards,
} from './document-card.harness';

/**
 * @sw-package after-sales
 */

describe('sw-order-document-card', () => {
    afterEach(unmountDocumentCards);

    /**
     * @regression https://github.com/shopware/shopware/issues/21619
     * After switching from one order detail page to another without a reload, the card kept the documents
     * of the previous order, and "Send document" mailed that order's PDF to the customer of the open order.
     */
    it('shows the documents of the new order when the order changes', async () => {
        // ARRANGE
        setUpDocuments([createDocument('documentOfOrderA')]);
        const wrapper = await mountDocumentCard({ order: createOrder({ id: 'orderA' }) });
        setUpDocuments([createDocument('documentOfOrderB', { orderId: 'orderB' })]);

        // ACT
        await wrapper.setProps({ order: createOrder({ id: 'orderB' }) });
        await flushPromises();

        // ASSERT
        expect(getDocumentSearches().at(-1)?.filters).toEqual([
            { type: 'equals', field: 'order.id', value: 'orderB' },
        ]);
        expect(getShownDocumentNumbers(wrapper)).toEqual(['documentOfOrderB-number']);
    });

    /**
     * @regression https://github.com/shopware/shopware/issues/21619
     * When the documents of the previous order arrived after the switch, they replaced the documents of the open order.
     */
    it('ignores documents of the previous order that arrive after the order changed', async () => {
        // ARRANGE
        setUpDocuments([createDocument('documentOfOrderA')]);
        const sendSearchOfOrderA = holdNextDocumentSearch();
        const wrapper = await mountDocumentCard({ order: createOrder({ id: 'orderA' }) });
        setUpDocuments([createDocument('documentOfOrderB', { orderId: 'orderB' })]);
        await wrapper.setProps({ order: createOrder({ id: 'orderB' }) });
        await flushPromises();

        // ACT
        await sendSearchOfOrderA();

        // ASSERT
        expect(getShownDocumentNumbers(wrapper)).toEqual(['documentOfOrderB-number']);
    });

    /**
     * @regression https://github.com/shopware/shopware/issues/21619
     * The card reloads for another order only, so editing the open order does not search its documents again.
     */
    it('does not search again when the order changes without changing its id', async () => {
        // ARRANGE
        const wrapper = await mountDocumentCard({ order: createOrder({ id: 'orderA' }) });

        // ACT
        await wrapper.setProps({ order: createOrder({ id: 'orderA', orderNumber: '10001' }) });
        await flushPromises();

        // ASSERT
        expect(getDocumentSearches()).toHaveLength(1);
    });
});
