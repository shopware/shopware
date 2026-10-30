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
        const handler = createHandler([createOrder(['first-delivery', 'second-delivery'])]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderDeliveries', value: 'ship' }]);

        expect(transitionOrderDeliveryState).toHaveBeenCalledWith(
            'first-delivery',
            'ship',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });

    it('loads the deliveries of the selected orders unsorted', () => {
        const handler = createHandler([]);
        handler.entityIds = ['order-id'];

        expect(handler.getCriteria().getAssociation('deliveries').sortings).toEqual([]);
    });
});
