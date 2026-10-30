import EntityCollection from 'src/core/data/entity-collection.data';
import BulkEditOrderHandler from '../index';

/**
 * @sw-package checkout
 */

const transitionOrderDeliveryState = jest.fn(() => Promise.resolve());

function createHandler(orders: unknown[]) {
    return Object.assign(new BulkEditOrderHandler(), {
        orderRepository: { search: () => Promise.resolve(orders) },
        orderStateMachineService: { transitionOrderDeliveryState },
    });
}

function createOrder(deliveryIds: string[]) {
    return {
        id: 'order-id',
        deliveries: new EntityCollection(
            '/order-delivery',
            'order_delivery',
            null,
            null,
            deliveryIds.map((id) => ({ id })),
        ),
    };
}

describe('bulk-edit-order', () => {
    it('transitions the first loaded delivery of each order', async () => {
        // ARRANGE
        const handler = createHandler([createOrder(['first-delivery', 'second-delivery'])]);

        // ACT
        await handler.bulkEditStatus(['order-id'], [{ field: 'orderDeliveries', value: 'ship' }]);

        // ASSERT
        expect(transitionOrderDeliveryState).toHaveBeenCalledWith(
            'first-delivery',
            'ship',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });

    it('loads the deliveries of the selected orders unsorted', () => {
        // ARRANGE
        const handler = createHandler([]);
        handler.entityIds = ['order-id'];

        // ACT
        const criteria = handler.getCriteria();

        // ASSERT
        expect(criteria.getAssociation('deliveries').sortings).toEqual([]);
    });
});
