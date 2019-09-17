const { Filter } = Shopware;

Filter.register('asset', (value) => {
    if (!value) {
        return '';
    }

    const context = Shopware.Context.get();

    return `${context.assetsPath}${value}`;
});
