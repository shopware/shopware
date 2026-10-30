import { createDeliveryStateGetter } from './order-list.harness';

/**
 * @sw-package checkout
 */

describe('sw-order-list', () => {
    it('shows the state of the primary delivery', async () => {
        const getDeliveryState = await createDeliveryStateGetter();
        const primary = { name: 'Shipped' };

        expect(
            getDeliveryState({
                primaryOrderDelivery: { stateMachineState: primary },
                deliveries: [{ id: 'other', stateMachineState: { name: 'Open' } }],
            }),
        ).toBe(primary);
    });
});
