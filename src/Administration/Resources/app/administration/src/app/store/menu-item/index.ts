/**
 * @sw-package framework
 *
 * The `menuItem` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/menuItem`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { menuItemStoreId, useMenuItemStore } from './src/menu-item.store';

/** @public */
export type { MenuItemEntry, MenuItemStore } from './src/menu-item.store';
