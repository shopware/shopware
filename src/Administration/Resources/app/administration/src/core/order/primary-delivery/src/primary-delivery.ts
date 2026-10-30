import { isFeatureActive } from '../dependencies';

/**
 * @sw-package checkout
 */

type OrderDelivery = EntitySchema.Entity<'order_delivery'>;

/**
 * The fields of an order this module reads.
 * Before 6.8 the deliveries back the fallback.
 *
 * @public
 */
export type OrderWithDeliveries = {
    primaryOrderDelivery?: OrderDelivery | null;
    deliveries?: OrderDelivery[] | null;
};

/**
 * Returns the delivery an order is shown with, which is its primary delivery.
 * Before 6.8 an order can have none, and then this returns its first loaded delivery.
 *
 * @example
 * const state = getPrimaryDelivery(order)?.stateMachineState;
 *
 * @public
 */
export function getPrimaryDelivery(order: OrderWithDeliveries): OrderDelivery | null {
    if (!isFeatureActive('v6.8.0.0')) {
        return order.primaryOrderDelivery ?? order.deliveries?.[0] ?? null;
    }

    return order.primaryOrderDelivery ?? null;
}
