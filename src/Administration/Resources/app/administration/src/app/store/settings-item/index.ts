/**
 * @sw-package framework
 *
 * The `settingsItems` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/settingsItems`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { settingsItemsStoreId, useSettingsItemsStore } from './src/settings-item.store';

/** @public */
export type { SettingsItem, SettingsItems } from './src/settings-item.store';
