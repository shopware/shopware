import { createDeliveryState, showDeliveryStates } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    it('shows the state of the primary delivery', async () => {
        // ARRANGE
        const order = {
            primaryOrderDelivery: { stateMachineState: createDeliveryState('Shipped') },
            deliveries: [{ id: 'other', stateMachineState: createDeliveryState('Open') }],
        };

        // ACT
        const shown = await showDeliveryStates([order]);

        // ASSERT
        expect(shown).toEqual(['Shipped']);
    });
});
