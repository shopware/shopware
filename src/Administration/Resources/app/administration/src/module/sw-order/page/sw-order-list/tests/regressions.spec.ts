import { createDeliveryStateGetter } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    /**
     * @regression #21760 For an order with several deliveries and no primary delivery,
     * the list showed the delivery as "Open" while the detail page showed it as "Shipped".
     * The list took the first delivery in loading order, and the detail page the most expensive one.
     */
    it.deprecated('v6.8.0.0')('shows the same delivery as the order detail page', async () => {
        const getDeliveryState = await createDeliveryStateGetter();
        const shipped = { name: 'Shipped' };

        expect(
            getDeliveryState({
                primaryOrderDelivery: null,
                deliveries: [
                    { id: 'cheap', shippingCosts: { unitPrice: 5 }, stateMachineState: { name: 'Open' } },
                    { id: 'expensive', shippingCosts: { unitPrice: 10 }, stateMachineState: shipped },
                ],
            }),
        ).toBe(shipped);
    });
});
