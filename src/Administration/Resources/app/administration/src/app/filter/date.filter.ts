import { format } from 'shopware:utils';

/**
 * @sw-package framework
 */

Shopware.Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return format.date(value, options);
});

/**
 * @private
 */
export default {};
