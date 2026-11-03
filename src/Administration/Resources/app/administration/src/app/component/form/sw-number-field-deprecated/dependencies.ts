/**
 * @sw-package framework
 *
 * Everything sw-number-field-deprecated needs from outside its mini-module.
 */
/** @private */
export { inject } from 'vue';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
