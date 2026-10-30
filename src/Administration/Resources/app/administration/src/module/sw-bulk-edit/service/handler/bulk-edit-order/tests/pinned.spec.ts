import BulkEditOrderHandler from '../index';

/**
 * @sw-package checkout
 */

describe('bulk-edit-order', () => {
    it('loads the deliveries of the selected orders unsorted', () => {
        const handler = new BulkEditOrderHandler();
        handler.entityIds = ['order-id'];

        expect(handler.getCriteria().getAssociation('deliveries').sortings).toEqual([]);
    });
});
