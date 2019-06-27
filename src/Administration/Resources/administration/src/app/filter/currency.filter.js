import { currency } from 'src/core/service/utils/format.utils';
import { Filter } from 'src/core/shopware';

Filter.register('currency', (value, format, decimalPlaces) => {
    if (format === undefined || format === 'default') {
        format = 'EUR';
    }

    if (value === null) {
        return '-';
    }
    return currency(value, format, decimalPlaces);
});
