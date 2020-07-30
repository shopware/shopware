const { Filter } = Shopware;

Filter.register('asset', (value) => {
    if (!value) {
        return '';
    }

    // Asset path already stars with an slash. Double slashes does not work on external storage like s3
    if (value[0] === '/') {
        value = value.substr(1);
    }

    return `${Shopware.Context.api.assetsPath}${value}`;
});
