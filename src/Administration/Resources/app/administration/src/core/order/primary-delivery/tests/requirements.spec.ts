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
    /**
     * @requirement An order is shown with its primary delivery.
     * The storefront, flows and documents use that delivery too, so merchants see the same state everywhere.
     */
    it('shows an order with its primary delivery', () => {
        const primary = delivery('primary', 5);

        expect(
            getPrimaryDelivery(createOrder({ primaryOrderDelivery: primary, deliveries: [delivery('other', 10), primary] })),
        ).toBe(primary);
    });

    /**
     * @requirement Until 6.8, an order without a primary delivery is shown with its most expensive delivery.
     * The 6.8 update makes that delivery the primary one, so the state merchants see stays the same.
     *
     * @deprecated tag:v6.8.0 - Removed with the 6.7 fallback.
     */
    it.deprecated('v6.8.0.0')('shows an order without a primary delivery with its most expensive delivery', () => {
        const expensive = delivery('expensive', 10);

        expect(
            getPrimaryDelivery(createOrder({ primaryOrderDelivery: null, deliveries: [delivery('cheap', 5), expensive] })),
        ).toBe(expensive);
    });
});
