import { fileSize } from 'shopware:utils/format';

/**
 * @sw-package framework
 */

/**
 * @private
 */
Shopware.Filter.register('fileSize', (value: number, locale: string) => {
    if (!value) {
        return '';
    }

    return fileSize(value, locale);
});

/* @private */
export {};
