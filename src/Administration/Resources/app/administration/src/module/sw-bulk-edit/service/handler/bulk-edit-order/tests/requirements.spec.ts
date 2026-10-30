import EntityCollection from 'src/core/data/entity-collection.data';
import BulkEditOrderHandler from '../index';

/**
 * @sw-package checkout
 */

describe('bulk-edit-order', () => {
    /**
     * @requirement Changing the delivery state of orders in bulk changes the delivery the order list shows.
     * Merchants select orders by the state they see in the list, see #21760.
     */
    it('changes the state of the delivery the order list shows', async () => {
        const transitionOrderDeliveryState = jest.fn(() => Promise.resolve());
        const order = {
            id: 'order-id',
            primaryOrderDeliveryId: 'primary-delivery',
            deliveries: new EntityCollection('/order-delivery', 'order_delivery', null, null, [
                { id: 'other-delivery' },
                { id: 'primary-delivery' },
            ]),
        };
        const handler = Object.assign(new BulkEditOrderHandler(), {
            orderRepository: { search: () => Promise.resolve([order]) },
            orderStateMachineService: { transitionOrderDeliveryState },
        });

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderDeliveries', value: 'ship' }]);

        expect(transitionOrderDeliveryState).toHaveBeenCalledWith(
            'primary-delivery',
            'ship',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });
});
