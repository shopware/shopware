/**
 * @sw-package framework
 */

/**
 * @private
 * The name the filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const dateFilterName = 'date';

Shopware.Filter.register(dateFilterName, (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return Shopware.Utils.format.date(value, options);
});

/**
 * @private
 */
export default {};
