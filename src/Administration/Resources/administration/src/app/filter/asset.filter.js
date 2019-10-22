const { Filter } = Shopware;

Filter.register('asset', (value) => {
    if (!value) {
        return '';
    }

    return `${Shopware.Context.Api.assetsPath}${value}`;
});
