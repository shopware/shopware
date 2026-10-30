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
        const order = createOrder({ primaryOrderDeliveryId: 'primary', deliveries: [delivery('other', 10), primary] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBe(primary);
    });

    /** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
    it.deprecated('v6.8.0.0')('picks the lowest id among deliveries with equal shipping costs', () => {
        const lowest = delivery('a', 5);
        const order = createOrder({ deliveries: [delivery('b', 5), lowest] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBe(lowest);
    });

    /** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
    it.deprecated('v6.8.0.0')('counts a delivery without shipping costs as free', () => {
        const paid = delivery('b', 1);
        const order = createOrder({ deliveries: [delivery('a'), paid] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBe(paid);
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

    it.activeFeatureFlags(['v6.8.0.0'])('ignores deliveries without an id when the order has no primary delivery id', () => {
        const order = createOrder({ deliveries: [{ shippingCosts: { unitPrice: 5 } }] });

        const shown = getPrimaryDelivery(order);

        expect(shown).toBeNull();
    });
});
