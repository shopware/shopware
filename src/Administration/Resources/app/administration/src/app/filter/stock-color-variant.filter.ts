/**
 * @private
 * The name the stockColorVariant filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const stockColorVariantFilterName = 'stockColorVariant';

/**
 * @sw-package inventory
 */

Shopware.Filter.register(stockColorVariantFilterName, (value: number) => {
    if (typeof value !== 'number' && !value) {
        return '';
    }

    if (value >= 25) {
        return 'success';
    }

    if (value < 25 && value > 0) {
        return 'warning';
    }

    return 'error';
});

/* @private */
export {};
