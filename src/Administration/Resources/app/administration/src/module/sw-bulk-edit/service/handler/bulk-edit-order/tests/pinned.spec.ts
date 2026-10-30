import BulkEditOrderHandler from '../index';

/**
 * @sw-package checkout
 */

describe('bulk-edit-order', () => {
    it('loads the deliveries of the selected orders unsorted', () => {
        // ARRANGE
        const handler = new BulkEditOrderHandler();
        handler.entityIds = ['order-id'];

        // ACT
        const criteria = handler.getCriteria();

        // ASSERT
        expect(criteria.getAssociation('deliveries').sortings).toEqual([]);
    });
});
