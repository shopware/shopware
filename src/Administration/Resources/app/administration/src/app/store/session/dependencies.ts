/**
 * @sw-package framework
 *
 * Everything session store needs from outside its mini-module.
 */
/** @private */
export { default as useSession } from 'src/app/composables/use-session';

/** @private */
export type { SetupStoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
