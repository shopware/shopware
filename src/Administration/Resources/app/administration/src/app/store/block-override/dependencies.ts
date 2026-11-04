/**
 * @sw-package framework
 *
 * Everything blockOverride store needs from outside its mini-module.
 */
/** @private */
export { default as useBlockContext } from 'src/app/composables/use-block-context';

/** @private */
export type { SetupStoreOf } from 'src/app/store/define-store-options';

/** @private */
export const getStore = <Id extends keyof PiniaRootState>(id: Id) => Shopware.Store.get(id);
