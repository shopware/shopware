/**
 * @sw-package framework
 *
 * Everything validation mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';

/** @private */
export const isArray = ((...args: unknown[]) =>
    (Shopware.Utils.types.isArray as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isArray;

/** @private */
export const isBoolean = ((...args: unknown[]) =>
    (Shopware.Utils.types.isBoolean as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isBoolean;

/** @private */
export const isString = ((...args: unknown[]) =>
    (Shopware.Utils.types.isString as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isString;
