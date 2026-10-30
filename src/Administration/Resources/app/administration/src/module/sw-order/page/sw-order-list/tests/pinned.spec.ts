import { createDeliveryStateGetter } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    it('shows the state of the primary delivery', async () => {
        const getDeliveryState = await createDeliveryStateGetter();
        const primary = { name: 'Shipped' };
        const order = {
            primaryOrderDelivery: { stateMachineState: primary },
            deliveries: [{ id: 'other', stateMachineState: { name: 'Open' } }],
        };

        const shown = getDeliveryState(order);

        expect(shown).toBe(primary);
    });
});
