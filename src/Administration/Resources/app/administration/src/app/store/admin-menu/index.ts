/**
 * @sw-package framework
 *
 * The `adminMenu` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/adminMenu`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { adminMenuStoreId, useAdminMenuStore } from './src/admin-menu.store';

/** @public */
export type { AdminMenuStore } from './src/admin-menu.store';
