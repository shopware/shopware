/**
 * @sw-package framework
 */

describe('src/app/store/sw-bulk-edit.store', () => {
    it('should be able to setIsFlowTriggered', async () => {
        const state = Shopware.Store.get('swBulkEdit');

        Shopware.Store.get('swBulkEdit').setIsFlowTriggered(true);
        expect(state.isFlowTriggered).toBe(true);

        Shopware.Store.get('swBulkEdit').setIsFlowTriggered(false);
        expect(state.isFlowTriggered).toBe(false);
    });

    it('should be able to setOrderDocumentsIsChanged', async () => {
        const state = Shopware.Store.get('swBulkEdit');

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: true,
        });
        expect(state.orderDocuments.invoice.isChanged).toBe(true);

        Shopware.Store.get('swBulkEdit').setOrderDocumentsIsChanged({
            type: 'invoice',
            isChanged: false,
        });
        expect(state.orderDocuments.invoice.isChanged).toBe(false);
    });

    it('should be able to resetOrderDocumentsIsChanged', async () => {
        const store = Shopware.Store.get('swBulkEdit');

        store.setOrderDocumentsIsChanged({ type: 'invoice', isChanged: true });
        store.setOrderDocumentsIsChanged({ type: 'delivery_note', isChanged: true });
        store.setOrderDocumentsIsChanged({ type: 'storno', isChanged: true });

        expect(store.orderDocuments.invoice.isChanged).toBe(true);
        expect(store.orderDocuments.delivery_note.isChanged).toBe(true);
        expect(store.orderDocuments.storno.isChanged).toBe(true);

        store.resetOrderDocumentsIsChanged();

        expect(store.orderDocuments.invoice.isChanged).toBe(false);
        expect(store.orderDocuments.storno.isChanged).toBe(false);
        expect(store.orderDocuments.delivery_note.isChanged).toBe(false);
        expect(store.orderDocuments.credit_note.isChanged).toBe(false);
        expect(store.orderDocuments.download.isChanged).toBe(false);
    });
});
