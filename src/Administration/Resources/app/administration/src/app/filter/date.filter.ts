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
 * @deprecated tag:v6.6.0 - Will be private
 */
export default {};
