export default {
    hydrate
};

/**
 * This function modifies and enhances the
 * raw properties of the provided promotion with
 * additional properties, getters and more.
 * new available properties:
 *      - promotion.hasOrders
 * @param {Object} promotion
 */
function hydrate(promotion) {
    promotion.hasOrders = (promotion.orderCount !== null) ? promotion.orderCount > 0 : false;
}
