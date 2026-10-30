import { isFeatureActive } from '../dependencies';

/**
 * @sw-package checkout
 */

type OrderDelivery = EntitySchema.Entity<'order_delivery'>;

/**
 * The fields of an order this module reads.
 * Load the primary delivery itself, or its id together with the deliveries.
 * Before 6.8 the deliveries also back the fallback.
 */
export type OrderWithDeliveries = {
    primaryOrderDelivery?: OrderDelivery | null;
    primaryOrderDeliveryId?: string | null;
    deliveries?: OrderDelivery[] | null;
};

/**
 * Returns the delivery an order is shown with, which is its primary delivery.
 * Before 6.8 an order can have none, and then this returns its most expensive loaded delivery,
 * which is the one the 6.8 update makes primary.
 *
 * @example
 * const state = getPrimaryDelivery(order)?.stateMachineState;
 */
export function getPrimaryDelivery(order: OrderWithDeliveries): OrderDelivery | null {
    const primary = order.primaryOrderDelivery ?? findById(order.deliveries ?? [], order.primaryOrderDeliveryId);

    if (primary || isFeatureActive('v6.8.0.0')) {
        return primary;
    }

    return findMostExpensive(order.deliveries ?? []);
}

function findById(deliveries: OrderDelivery[], id: string | null | undefined): OrderDelivery | null {
    if (!id) {
        return null;
    }

    return deliveries.find((delivery) => delivery.id === id) ?? null;
}

/**
 * Picks the delivery with the highest shipping costs, and of equal costs the one with the lowest id.
 * Migration1777952600BackfillPrimaryOrderDelivery picks the primary delivery in the same order.
 */
function findMostExpensive(deliveries: OrderDelivery[]): OrderDelivery | null {
    return deliveries.reduce<OrderDelivery | null>((best, delivery) => {
        if (!best) {
            return delivery;
        }

        const difference = getShippingCosts(delivery) - getShippingCosts(best);

        return difference > 0 || (difference === 0 && delivery.id < best.id) ? delivery : best;
    }, null);
}

function getShippingCosts(delivery: OrderDelivery): number {
    return (delivery.shippingCosts as { unitPrice?: number } | undefined)?.unitPrice ?? 0;
}
