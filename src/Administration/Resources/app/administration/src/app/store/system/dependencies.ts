/**
 * @sw-package framework
 *
 * Everything system store needs from outside its mini-module.
 */
/** @private */
export { default as useSystem } from 'src/app/composables/use-system';

/** @private */
export type { SetupStoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
