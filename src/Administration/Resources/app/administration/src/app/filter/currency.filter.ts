/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';
import { currency } from 'shopware:utils/format';
import { isNumber, isEqual } from 'shopware:utils/types';

/**
 * @private
 */
Shopware.Filter.register(
    'currency',
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if ((!value || value === true) && (!isNumber(value) || isEqual(value, NaN))) {
            return '-';
        }

        if (isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
