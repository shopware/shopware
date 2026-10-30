import { getPrimaryDelivery, type OrderWithDeliveries } from '../index';

/**
 * @sw-package checkout
 */

function delivery(id: string, unitPrice?: number) {
    return unitPrice === undefined ? { id } : { id, shippingCosts: { unitPrice } };
}

function createOrder(order: Record<string, unknown>): OrderWithDeliveries {
    return order as OrderWithDeliveries;
}

describe('primary-delivery', () => {
    it('finds the primary delivery by its id among the loaded deliveries', () => {
        const primary = delivery('primary', 5);

        expect(
            getPrimaryDelivery(
                createOrder({ primaryOrderDeliveryId: 'primary', deliveries: [delivery('other', 10), primary] }),
            ),
        ).toBe(primary);
    });

    it.deprecated('v6.8.0.0')('picks the lowest id among deliveries with equal shipping costs', () => {
        const lowest = delivery('a', 5);

        expect(getPrimaryDelivery(createOrder({ deliveries: [delivery('b', 5), lowest] }))).toBe(lowest);
    });

    it.deprecated('v6.8.0.0')('counts a delivery without shipping costs as free', () => {
        const paid = delivery('b', 1);

        expect(getPrimaryDelivery(createOrder({ deliveries: [delivery('a'), paid] }))).toBe(paid);
    });

    it('returns null for an order without deliveries', () => {
        expect(getPrimaryDelivery(createOrder({ primaryOrderDelivery: null, deliveries: null }))).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('has no fallback in 6.8', () => {
        expect(getPrimaryDelivery(createOrder({ deliveries: [delivery('a', 5)] }))).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('ignores deliveries without an id when the order has no primary delivery id', () => {
        expect(getPrimaryDelivery(createOrder({ deliveries: [{ shippingCosts: { unitPrice: 5 } }] }))).toBeNull();
    });
});
