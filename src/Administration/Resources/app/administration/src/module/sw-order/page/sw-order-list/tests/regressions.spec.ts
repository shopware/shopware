import { createDeliveryState, showDeliveryStates } from './order-list.harness';

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
        // ARRANGE
        const order = {
            primaryOrderDelivery: null,
            deliveries: [
                { id: 'cheap', shippingCosts: { unitPrice: 5 }, stateMachineState: createDeliveryState('Open') },
                { id: 'expensive', shippingCosts: { unitPrice: 10 }, stateMachineState: createDeliveryState('Shipped') },
            ],
        };

        // ACT
        const shown = await showDeliveryStates([order]);

        // ASSERT
        expect(shown).toEqual(['Shipped']);
    });
});
