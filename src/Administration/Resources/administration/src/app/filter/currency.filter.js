const { Filter } = Shopware;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces) => {
    if (value === null) {
        return '-';
    }
    return currency(value, format, decimalPlaces);
});
