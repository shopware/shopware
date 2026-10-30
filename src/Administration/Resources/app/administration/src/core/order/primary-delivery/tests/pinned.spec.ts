import { getPrimaryDelivery, type OrderWithDeliveries } from '../index';

/**
 * @sw-package checkout
 */

function delivery(id: string, unitPrice: number) {
    return { id, shippingCosts: { unitPrice } };
}

function createOrder(order: Record<string, unknown>): OrderWithDeliveries {
    return order as OrderWithDeliveries;
}

describe('primary-delivery', () => {
    /** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
    it.deprecated('v6.8.0.0')('falls back to the first loaded delivery', () => {
        const first = delivery('first', 5);
        const order = createOrder({ primaryOrderDelivery: null, deliveries: [first, delivery('second', 10)] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBe(first);
    });

    it('returns null for an order without deliveries', () => {
        const order = createOrder({ primaryOrderDelivery: null, deliveries: null });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('has no fallback in 6.8', () => {
        const order = createOrder({ deliveries: [delivery('a', 5)] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBeNull();
    });
});
