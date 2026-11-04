/**
 * @sw-package framework
 *
 * Everything error store needs from outside its mini-module.
 */
/** @private */
export type { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as Utils } from 'src/core/service/util.service';

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
