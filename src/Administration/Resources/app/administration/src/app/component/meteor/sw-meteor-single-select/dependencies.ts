/**
 * @sw-package framework
 *
 * Everything sw-meteor-single-select needs from outside its mini-module.
 */
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const debounce = ((...args: unknown[]) =>
    (Shopware.Utils.debounce as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debounce;

/** @private */
export const get = ((...args: unknown[]) =>
    (Shopware.Utils.get as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.get;
