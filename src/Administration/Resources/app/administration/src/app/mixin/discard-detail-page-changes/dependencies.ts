/**
 * @sw-package framework
 *
 * Everything discard-detail-page-changes mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { NavigationGuardNext } from 'vue-router';
/** @private */
export { useErrorStore } from 'src/app/store/error';

/** @private */
export const isArray = ((...args: unknown[]) =>
    (Shopware.Utils.types.isArray as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isArray;

/** @private */
export const isString = ((...args: unknown[]) =>
    (Shopware.Utils.types.isString as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isString;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
