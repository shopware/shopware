/**
 * @sw-package framework
 *
 * Everything shopwareApps store needs from outside its mini-module.
 */
/** @private */
export type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';

/** @private */
export { defineStoreOptions, type StoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
