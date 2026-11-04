/**
 * @sw-package framework
 *
 * The `modals` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/modals`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { modalsStoreId, useModalsStore } from './src/modals.store';

/** @public */
export type { ModalItemEntry, ModalsStore } from './src/modals.store';
