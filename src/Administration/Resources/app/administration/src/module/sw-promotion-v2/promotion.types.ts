/**
 * @package checkout
 */

enum DiscountTypes {
    PERCENTAGE = 'percentage',
    ABSOLUTE = 'absolute',
    FIXED = 'fixed',
    FIXED_UNIT = 'fixed_unit',
}

enum DiscountScopes {
    CART = 'cart',
    DELIVERY = 'delivery',
    SET = 'set',
    SETGROUP = 'setgroup',
}

/**
 * @private
 */
export {
    DiscountTypes,
    DiscountScopes,
};
