/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';
import { types } from 'shopware:utils';
import { currency } from 'shopware:utils/format';

/**
 * @private
 */
Shopware.Filter.register(
    'currency',
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if ((!value || value === true) && (!types.isNumber(value) || types.isEqual(value, NaN))) {
            return '-';
        }

        if (types.isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
