/**
 * @sw-package framework
 *
 * The `marketing` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/marketing`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { marketingStoreId, useMarketingStore } from './src/marketing.store';

/** @public */
export type { MarketingStore } from './src/marketing.store';
