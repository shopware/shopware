/**
 * @sw-package inventory
 *
 * Everything the swProductDetail store needs from outside its mini-module.
 */

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);

/** @private */
export type { default as EntityCollection } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

/** @private */
export type { ContextStore } from 'src/app/store/context.store';
