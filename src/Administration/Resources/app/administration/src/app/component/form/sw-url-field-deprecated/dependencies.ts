/**
 * @sw-package framework
 *
 * Everything sw-url-field-deprecated needs from outside its mini-module.
 */
import { unicodeUriFilterName } from 'src/app/filter/unicode-uri';

/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const getUnicodeUriFilter = () => Shopware.Filter.getByName(unicodeUriFilterName);
