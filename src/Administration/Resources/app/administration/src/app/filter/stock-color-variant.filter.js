const { Filter } = Shopware;

Filter.register('stockColorVariant', (value) => {
    if (!value) {
        return '';
    }

    if (value >= 25) {
        return 'success';
    }

    if (value < 25 && value > 0) {
        return 'warning';
    }

    return 'error';
});
