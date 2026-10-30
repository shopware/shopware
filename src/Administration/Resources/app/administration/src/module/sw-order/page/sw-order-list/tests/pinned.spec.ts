import { createDeliveryStateGetter } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    it('shows the state of the primary delivery', async () => {
        // ARRANGE
        const getDeliveryState = await createDeliveryStateGetter();
        const primary = { name: 'Shipped' };
        const order = {
            primaryOrderDelivery: { stateMachineState: primary },
            deliveries: [{ id: 'other', stateMachineState: { name: 'Open' } }],
        };

        // ACT
        const shown = getDeliveryState(order);

        // ASSERT
        expect(shown).toBe(primary);
    });
});
