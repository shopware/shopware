/**
 * @package admin
 */

import Punycode from 'punycode';

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Shopware.Filter.register('unicodeUri', (value: string) => {
    if (!value) {
        return '';
    }

    const unicode = Punycode.toUnicode(value);

    return decodeURI(unicode);
});

/* @private */
export {};

