/**
 * @sw-package checkout
 */
import BulkEditApiFactory from 'src/module/sw-bulk-edit/service/bulk-edit.api.factory';
import EntityCollection from 'src/core/data/entity-collection.data';

function getBulkEditOrderHandler(orders) {
    const handler = new BulkEditApiFactory().getHandler('order');

    handler.orderRepository = {
        search: jest.fn(() => Promise.resolve(orders)),
    };
    handler.orderStateMachineService = {
        transitionOrderDeliveryState: jest.fn(() => Promise.resolve()),
    };

    return handler;
}

function createOrder(primaryOrderDeliveryId, deliveryIds) {
    return {
        id: 'order-id',
        primaryOrderDeliveryId,
        deliveries: new EntityCollection(
            '/order-delivery',
            'order_delivery',
            null,
            null,
            deliveryIds.map((id) => ({ id })),
        ),
    };
}

describe('module/sw-bulk-edit/service/handler/bulk-edit-order.handler', () => {
    it('should sort deliveries like the order list and the order detail page', () => {
        const handler = getBulkEditOrderHandler([]);

        expect(handler.getCriteria().getAssociation('deliveries').sortings).toEqual([
            expect.objectContaining({ field: 'shippingCosts.unitPrice', order: 'DESC' }),
            expect.objectContaining({ field: 'id', order: 'ASC' }),
        ]);
    });

    it('should transition the primary order delivery', async () => {
        const handler = getBulkEditOrderHandler([createOrder('primary-delivery', ['other-delivery', 'primary-delivery'])]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderDeliveries', value: 'ship' }]);

        expect(handler.orderStateMachineService.transitionOrderDeliveryState).toHaveBeenCalledWith(
            'primary-delivery',
            'ship',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });

    it('should transition the first delivery when the order has no primary order delivery', async () => {
        const handler = getBulkEditOrderHandler([createOrder(null, ['first-delivery', 'second-delivery'])]);

        await handler.bulkEditStatus(['order-id'], [{ field: 'orderDeliveries', value: 'ship' }]);

        expect(handler.orderStateMachineService.transitionOrderDeliveryState).toHaveBeenCalledWith(
            'first-delivery',
            'ship',
            expect.any(Object),
            {},
            expect.any(Object),
        );
    });
});
