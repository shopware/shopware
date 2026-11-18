/**
 * @sw-package checkout
 */
import type { CalculatedPrice } from './order.types';

type OrderDelivery = Entity<'order_delivery'>;

function isShippingDiscount(delivery: OrderDelivery): boolean {
    return (delivery.shippingCosts as CalculatedPrice).totalPrice < 0;
}

/**
 * @private
 */
export function getShipments(order: Entity<'order'>): OrderDelivery[] {
    if (!Shopware.Feature.isActive('v6.8.0.0')) {
        return Shopware.Utils.array.slice(order.deliveries, 0, 1);
    }

    const deliveries: OrderDelivery[] = order.deliveries ?? [];
    const shipments = deliveries.filter((delivery) => !isShippingDiscount(delivery));
    const primary = shipments.find((delivery) => delivery.id === order.primaryOrderDeliveryId);

    if (!primary) {
        return shipments;
    }

    return [primary, ...shipments.filter((delivery) => delivery !== primary)];
}

/**
 * @private
 */
export function getDeliveryDiscounts(order: Entity<'order'>): OrderDelivery[] {
    if (!Shopware.Feature.isActive('v6.8.0.0')) {
        return Shopware.Utils.array.slice(order.deliveries, 1);
    }

    const deliveries: OrderDelivery[] = order.deliveries ?? [];

    return deliveries.filter(isShippingDiscount);
}
