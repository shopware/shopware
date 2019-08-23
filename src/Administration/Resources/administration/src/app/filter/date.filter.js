const { Filter } = Shopware;
const { date } = Shopware.Utils.format;

Filter.register('date', (value, options) => {
    if (!value) {
        return '';
    }

    return date(value, options);
});
