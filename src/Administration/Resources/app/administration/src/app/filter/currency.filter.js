const { Filter } = Shopware;
const { types } = Shopware.Utils;
const { currency } = Shopware.Utils.format;

Filter.register('currency', (value, format, decimalPlaces) => {
    if ((!value || value === true) && (!types.isNumber(value) || types.isEqual(value, NaN))) {
        return '-';
    }

    if (types.isEqual(parseInt(value, 10), NaN)) {
        return value;
    }

    return currency(parseFloat(value), format, decimalPlaces);
});
