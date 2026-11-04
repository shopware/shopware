/**
 * @sw-package framework
 *
 * The `shopwareApps` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/shopwareApps`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { shopwareAppsStoreId, useShopwareAppsStore } from './src/shopware-apps.store';

/** @public */
export type { ShopwareAppsState, ShopwareApps } from './src/shopware-apps.store';
