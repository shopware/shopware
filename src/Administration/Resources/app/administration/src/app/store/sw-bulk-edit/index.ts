/**
 * @sw-package framework
 *
 * The `swBulkEdit` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/swBulkEdit`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { swBulkEditStoreId, useSwBulkEditStore } from './src/sw-bulk-edit.store';

/** @public */
export type { SwBulkStore } from './src/sw-bulk-edit.store';
