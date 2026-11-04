/**
 * @sw-package framework
 *
 * Everything sw-inline-snippet mixin needs from outside its mini-module.
 */
/** @private */
export { defineComponent } from 'vue';
/** @private */
export { useSessionStore } from 'src/app/store/session';

/** @private */
export const getAppContext = () => Shopware.Context.app;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export const isObject = ((...args: unknown[]) =>
    (Shopware.Utils.types.isObject as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isObject;
