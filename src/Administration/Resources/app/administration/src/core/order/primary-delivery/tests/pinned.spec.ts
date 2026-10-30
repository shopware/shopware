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
    it.deprecated('v6.8.0.0')('falls back to the first loaded delivery', () => {
        const first = delivery('first', 5);

        expect(
            getPrimaryDelivery(createOrder({ primaryOrderDelivery: null, deliveries: [first, delivery('second', 10)] })),
        ).toBe(first);
    });

    it('returns null for an order without deliveries', () => {
        expect(getPrimaryDelivery(createOrder({ primaryOrderDelivery: null, deliveries: null }))).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('has no fallback in 6.8', () => {
        expect(getPrimaryDelivery(createOrder({ deliveries: [delivery('a', 5)] }))).toBeNull();
    });
});
