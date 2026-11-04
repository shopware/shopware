/**
 * @sw-package framework
 *
 * The `sidebar` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/sidebar`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { sidebarStoreId, useSidebarStore } from './src/sidebar.store';

/** @public */
export type { SidebarItemEntry, SidebarStore } from './src/sidebar.store';
