/**
 * @package system-settings
 */
import swBulkEditState from 'src/module/sw-bulk-edit/state/sw-bulk-edit.state';

describe('src/module/sw-bulk-edit/state/sw-bulk-edit.state', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    });

    it('should be able to setIsFlowTriggered', async () => {
        const state = Shopware.State.get('swBulkEdit');

        Shopware.State.commit('swBulkEdit/setIsFlowTriggered', true);
        expect(state.isFlowTriggered).toBe(true);

        Shopware.State.commit('swBulkEdit/setIsFlowTriggered', false);
        expect(state.isFlowTriggered).toBe(false);
    });

    it('should be able to setOrderDocumentsIsChanged', async () => {
        const state = Shopware.State.get('swBulkEdit');

        Shopware.State.commit('swBulkEdit/setOrderDocumentsIsChanged', {
            type: 'invoice',
            isChanged: true,
        });
        expect(state.orderDocuments.invoice.isChanged).toBe(true);

        Shopware.State.commit('swBulkEdit/setOrderDocumentsIsChanged', {
            type: 'invoice',
            isChanged: false,
        });
        expect(state.orderDocuments.invoice.isChanged).toBe(false);
    });
});
