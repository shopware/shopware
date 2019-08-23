const { Filter } = Shopware;

Filter.register('striphtml', (value) => {
    if (!value) {
        return '';
    }

    return value.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, '');
});
