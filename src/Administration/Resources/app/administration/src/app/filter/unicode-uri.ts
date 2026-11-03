/**
 * @sw-package framework
 */

import Punycode from 'punycode';

/**
 * @private
 * The name the unicodeUri filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const unicodeUriFilterName = 'unicodeUri';

/**
 * @private
 */
Shopware.Filter.register(unicodeUriFilterName, (value: string) => {
    if (!value) {
        return '';
    }

    const unicode = Punycode.toUnicode(value);

    return decodeURI(unicode);
});

/* @private */
export {};
