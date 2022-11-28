import { DiscountTypes } from 'src/module/sw-promotion-v2/helper/promotion.helper';

/**
 * @private
 */
export default class PromotionDiscountHandler {
    // Gets the suffix of the value text field depending
    // on the currently selected type.
    getValueSuffix(discountType, currencySymbol = '?') {
        return (discountType === DiscountTypes.PERCENTAGE) ? '%' : currencySymbol;
    }

    // Gets the value minimum threshold depending
    // on the currently selected type.
    getMinValue() {
        return 0.00;
    }

    // Gets the value maximum threshold depending
    // on the currently selected type.
    getMaxValue(discountType) {
        return (discountType === DiscountTypes.PERCENTAGE) ? 100 : null;
    }

    // This function verifies the currently set value
    // depending on the discount type, and fixes it if
    // the min or maximum thresholds have been exceeded.
    getFixedValue(value, discountType) {
        if (discountType === DiscountTypes.PERCENTAGE) {
            value = (value > 100) ? this.getMaxValue(discountType) : value;
        }
        if (value <= this.getMinValue()) {
            value = this.getMinValue();
        }

        return value;
    }
}
