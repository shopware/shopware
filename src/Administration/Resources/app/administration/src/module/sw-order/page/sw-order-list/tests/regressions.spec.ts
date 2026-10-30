import { createDeliveryStateGetter } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    /**
     * @regression #21760 For an order with several deliveries and no primary delivery,
     * the list showed the delivery as "Open" while the detail page showed it as "Shipped".
     * The list took the first delivery in loading order, and the detail page the most expensive one.
     *
     * @deprecated tag:v6.8.0 - Removed with the 6.7 fallback.
     */
    it.deprecated('v6.8.0.0')('shows the same delivery as the order detail page', async () => {
        // ARRANGE
        const getDeliveryState = await createDeliveryStateGetter();
        const shipped = { name: 'Shipped' };
        const order = {
            primaryOrderDelivery: null,
            deliveries: [
                { id: 'cheap', shippingCosts: { unitPrice: 5 }, stateMachineState: { name: 'Open' } },
                { id: 'expensive', shippingCosts: { unitPrice: 10 }, stateMachineState: shipped },
            ],
        };

        // ACT
        const shown = getDeliveryState(order);

        // ASSERT
        expect(shown).toBe(shipped);
    });
});
