/**
 * @sw-package framework
 */

/**
 * @private
 * The name the filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const assetFilterName = 'asset';

Shopware.Filter.register(assetFilterName, (value: string) => {
    if (!value) {
        return '';
    }

    // Asset path already stars with an slash. Double slashes does not work on external storage like s3
    if (value[0] === '/') {
        value = value.substr(1);
    }

    const assetsPath = Shopware.Context.api.assetsPath || '';

    return `${assetsPath}${value}`;
});

/**
 * @private
 */
export {};
