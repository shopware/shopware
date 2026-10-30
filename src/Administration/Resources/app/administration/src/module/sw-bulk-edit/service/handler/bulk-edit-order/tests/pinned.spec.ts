import BulkEditOrderHandler from '../index';

/**
 * @sw-package checkout
 */

describe('bulk-edit-order', () => {
    it('loads the deliveries of the selected orders unsorted', () => {
        const handler = new BulkEditOrderHandler();
        handler.entityIds = ['order-id'];

        const criteria = handler.getCriteria();

        expect(criteria.getAssociation('deliveries').sortings).toEqual([]);
    });
});
