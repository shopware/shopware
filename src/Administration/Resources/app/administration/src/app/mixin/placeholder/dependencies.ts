/**
 * @sw-package framework
 *
 * Everything placeholder mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isString = ((...args: unknown[]) =>
    (Shopware.Utils.types.isString as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isString;
