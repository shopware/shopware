/**
 * @package admin
 */
import { toUnicode } from 'punycode/';

/**
 * @private
 */
Shopware.Filter.register('decode-idn-email', (
    value: string,
) => {
    return toUnicode(value);
});
