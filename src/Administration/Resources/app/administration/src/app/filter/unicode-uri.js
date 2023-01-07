/**
 * @package admin
 */

import Punycode from 'punycode';

const { Filter } = Shopware;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
Filter.register('unicodeUri', (value) => {
    if (!value) {
        return '';
    }

    const unicode = Punycode.toUnicode(value);

    return decodeURI(unicode);
});
