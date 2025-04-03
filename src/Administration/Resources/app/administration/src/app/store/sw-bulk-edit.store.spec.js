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
});
