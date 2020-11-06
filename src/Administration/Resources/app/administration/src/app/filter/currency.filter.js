const { Filter } = Shopware;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces, additionalOptions) => {
    if (value === null) {
        return '-';
    }
    return currency(value, format, decimalPlaces, additionalOptions);
});
