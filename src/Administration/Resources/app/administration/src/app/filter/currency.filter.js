const { Filter } = Shopware;
const { types } = Shopware.Utils;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces) => {
    if (!value && !types.isNumber(value)) {
        return '-';
    }
    return currency(value, format, decimalPlaces);
});
