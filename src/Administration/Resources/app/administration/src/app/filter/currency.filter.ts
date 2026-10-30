/**
 * @sw-package framework
 */
import type { CurrencyOptions } from 'src/core/service/utils/format.utils';

const { currency } = Shopware.Utils.format;

/**
 * @private
 * The name the filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const currencyFilterName = 'currency';

/**
 * @private
 */
Shopware.Filter.register(
    currencyFilterName,
    (value: string | boolean, format: string, decimalPlaces: number, additionalOptions: CurrencyOptions) => {
        if (
            (!value || value === true) &&
            (!Shopware.Utils.types.isNumber(value) || Shopware.Utils.types.isEqual(value, NaN))
        ) {
            return '-';
        }

        if (Shopware.Utils.types.isEqual(parseInt(value, 10), NaN)) {
            return value;
        }

        return currency(parseFloat(value), format, decimalPlaces, additionalOptions);
    },
);
