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
        // ARRANGE
        const primary = delivery('primary', 5);
        const order = createOrder({ primaryOrderDeliveryId: 'primary', deliveries: [delivery('other', 10), primary] });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBe(primary);
    });

    /** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
    it.deprecated('v6.8.0.0')('picks the lowest id among deliveries with equal shipping costs', () => {
        // ARRANGE
        const lowest = delivery('a', 5);
        const order = createOrder({ deliveries: [delivery('b', 5), lowest] });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBe(lowest);
    });

    /** @deprecated tag:v6.8.0 - Removed with the 6.7 fallback. */
    it.deprecated('v6.8.0.0')('counts a delivery without shipping costs as free', () => {
        // ARRANGE
        const paid = delivery('b', 1);
        const order = createOrder({ deliveries: [delivery('a'), paid] });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBe(paid);
    });

    it('returns null for an order without deliveries', () => {
        // ARRANGE
        const order = createOrder({ primaryOrderDelivery: null, deliveries: null });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('has no fallback in 6.8', () => {
        // ARRANGE
        const order = createOrder({ deliveries: [delivery('a', 5)] });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBeNull();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('ignores deliveries without an id when the order has no primary delivery id', () => {
        // ARRANGE
        const order = createOrder({ deliveries: [{ shippingCosts: { unitPrice: 5 } }] });

        // ACT
        const shown = getPrimaryDelivery(order);

        // ASSERT
        expect(shown).toBeNull();
    });
});
