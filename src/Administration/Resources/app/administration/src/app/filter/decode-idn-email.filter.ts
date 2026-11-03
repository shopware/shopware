/**
 * @sw-package framework
 */
import { toUnicode } from 'punycode/';

/**
 * @private
 * The name the decode-idn-email filter is registered under, for code that looks it up with `Shopware.Filter.getByName()`.
 */
export const decodeIdnEmailFilterName = 'decode-idn-email';

/**
 * @private
 */
Shopware.Filter.register(decodeIdnEmailFilterName, (value: string) => {
    return toUnicode(value);
});
