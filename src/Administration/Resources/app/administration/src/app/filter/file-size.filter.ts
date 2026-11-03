/**
 * @private
 * The name the fileSize filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const fileSizeFilterName = 'fileSize';

/**
 * @sw-package framework
 */

/**
 * @private
 */
Shopware.Filter.register(fileSizeFilterName, (value: number, locale: string) => {
    if (!value) {
        return '';
    }

    return Shopware.Utils.format.fileSize(value, locale);
});

/* @private */
export {};
