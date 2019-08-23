const { Filter } = Shopware;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces) => {
    if (format === undefined || format === 'default') {
        format = 'EUR';
    }

    if (value === null) {
        return '-';
    }
    return currency(value, format, decimalPlaces);
});
