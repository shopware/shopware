/**
 * @sw-package framework
 *
 * Everything sw-price-field needs from outside its mini-module.
 */
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;
