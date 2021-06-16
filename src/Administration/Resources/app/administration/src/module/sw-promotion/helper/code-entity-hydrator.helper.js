export default {
    hydrate,
};

/**
 * @deprecated tag:v6.5.0 - will be removed, use `sw-promotion-v2` instead
 * This function modifies and enhances the
 * raw properties of the provided item with
 * additional properties, getters and more.
 * new available properties:
 *      - item.isRedeemed
 *      - item.orderId
 *      - item.customerId
 *      - item.customerName
 * @param {Object} item
 */
function hydrate(item) {
    item.isRedeemed = false;
    item.orderId = null;
    item.customerId = null;
    item.customerName = null;

    if (item.payload !== null) {
        item.isRedeemed = true;
        item.orderId = item.payload.orderId;
        item.customerId = item.payload.customerId;
        item.customerName = item.payload.customerName;
    }
}
