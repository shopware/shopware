import { date } from 'shopware:utils/format';

/**
 * @sw-package framework
 */

Shopware.Filter.register('date', (value: string, options: Intl.DateTimeFormatOptions = {}): string => {
    if (!value) {
        return '';
    }

    return date(value, options);
});

/**
 * @private
 */
export default {};
