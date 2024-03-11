/**
 * @package admin
 */

import Punycode from 'punycode';

/**
 * @private
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

