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

    it.deprecated('v6.8.0.0')('falls back to the state of the first loaded delivery', async () => {
        const getDeliveryState = await createDeliveryStateGetter();
        const first = { name: 'Open' };

        expect(
            getDeliveryState({
                primaryOrderDelivery: null,
                deliveries: [
                    { id: 'first', stateMachineState: first },
                    { id: 'second', stateMachineState: { name: 'Shipped' } },
                ],
            }),
        ).toBe(first);
    });
});
