import { format } from 'shopware:utils';

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

    return format.fileSize(value, locale);
});

/* @private */
export {};
