/**
 * @package admin
 */

Shopware.Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return Shopware.Utils.format.date(value, options);
});

/**
 * @private
 */
export default {};
