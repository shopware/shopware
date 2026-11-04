/**
 * @sw-package framework
 *
 * Everything tabs store needs from outside its mini-module.
 */
/** @private */
export type { uiTabsAddTabItem, uiTabsSetVisibility } from '@shopware-ag/meteor-admin-sdk/es/ui/tabs';

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export type { Router, RouteRecordNameGeneric, RouteRecordRaw } from 'vue-router';
