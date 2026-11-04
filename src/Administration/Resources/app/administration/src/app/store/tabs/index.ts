/**
 * @sw-package framework
 *
 * The `tabs` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/tabs`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { tabsStoreId, useTabsStore } from './src/tabs.store';

/** @public */
export type { TabItemEntry, TabsStore } from './src/tabs.store';
