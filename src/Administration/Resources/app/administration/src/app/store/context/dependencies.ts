/**
 * @sw-package framework
 *
 * Everything context store needs from outside its mini-module.
 */
/** @private */
export { default as useContext } from 'src/app/composables/use-context';

/** @private */
export type { SetupStoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
