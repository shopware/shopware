const { Filter } = Shopware;
const { types } = Shopware.Utils;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces) => {
    if ((!value || value === true) && !types.isNumber(value)) {
        return '-';
    }
    return currency(value, format, decimalPlaces);
});
