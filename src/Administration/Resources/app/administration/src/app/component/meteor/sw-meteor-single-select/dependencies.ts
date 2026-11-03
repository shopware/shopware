/**
 * @sw-package framework
 *
 * Everything sw-meteor-single-select needs from outside its mini-module.
 */
/** @private */
export { default as removeApiErrorMixin } from 'src/app/mixin/remove-api-error.mixin';
/** @private */
export { feature as featureService } from 'src/app/service-names';

/** @private */
export const debounce = (...args: Parameters<typeof Shopware.Utils.debounce>) => Shopware.Utils.debounce(...args);

/** @private */
export const get = (...args: Parameters<typeof Shopware.Utils.get>) => Shopware.Utils.get(...args);
