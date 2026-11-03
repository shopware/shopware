/**
 * @sw-package framework
 *
 * Everything sw-price-field needs from outside its mini-module.
 */
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);
