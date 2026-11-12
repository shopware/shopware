/**
 * @sw-package checkout
 */
import { getDeliveryDiscounts, getShipments } from './order-delivery.helper';

function createDelivery(id: string, totalPrice: number): Entity<'order_delivery'> {
    return { id, shippingCosts: { totalPrice } } as unknown as Entity<'order_delivery'>;
}

const primaryShipment = createDelivery('primary-shipment', 4.99);
const secondShipment = createDelivery('second-shipment', 4.99);
const freeShipment = createDelivery('free-shipment', 0);
const discount = createDelivery('discount', -4.99);

function createOrder(primaryOrderDeliveryId: string | null): Entity<'order'> {
    return {
        primaryOrderDeliveryId,
        deliveries: [
            secondShipment,
            primaryShipment,
            discount,
            freeShipment,
        ],
    } as unknown as Entity<'order'>;
}

const order = createOrder('primary-shipment');

describe('src/module/sw-order/order-delivery.helper', () => {
    it.activeFeatureFlags(['v6.8.0.0'])('treats deliveries with negative shipping costs as discounts', () => {
        expect(getDeliveryDiscounts(order)).toEqual([discount]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('lists every other delivery as a shipment, the primary one first', () => {
        expect(getShipments(order)).toEqual([
            primaryShipment,
            secondShipment,
            freeShipment,
        ]);
    });

    it.activeFeatureFlags(['v6.8.0.0'])(
        'keeps the delivery order when none of the shipments is the primary delivery',
        () => {
            expect(getShipments(createOrder(null))).toEqual([
                secondShipment,
                primaryShipment,
                freeShipment,
            ]);
        },
    );

    it.activeFeatureFlags(['v6.8.0.0'])('classifies nothing for an order without deliveries', () => {
        const emptyOrder = { deliveries: [] } as unknown as Entity<'order'>;

        expect(getShipments(emptyOrder)).toEqual([]);
        expect(getDeliveryDiscounts(emptyOrder)).toEqual([]);
    });

    // @deprecated tag:v6.8.0 - The tests will be removed with the position based classification.
    it.deprecated('v6.8.0.0')('treats the first delivery as the shipment and every other delivery as a discount', () => {
        expect(getShipments(order)).toEqual([secondShipment]);
        expect(getDeliveryDiscounts(order)).toEqual([
            primaryShipment,
            discount,
            freeShipment,
        ]);
    });

    // @deprecated tag:v6.8.0 - The tests will be removed with the position based classification.
    it.deprecated('v6.8.0.0')('classifies nothing for an order without deliveries', () => {
        const emptyOrder = { deliveries: [] } as unknown as Entity<'order'>;

        expect(getShipments(emptyOrder)).toEqual([]);
        expect(getDeliveryDiscounts(emptyOrder)).toEqual([]);
    });
});
