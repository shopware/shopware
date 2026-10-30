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
});
