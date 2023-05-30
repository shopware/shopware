/**
 * @package admin
 */

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Filter.register('fileSize', (value: number, locale: string) => {
    if (!value) {
        return '';
    }

    return Shopware.Utils.format.fileSize(value, locale);
});

/* @private */
export {};
