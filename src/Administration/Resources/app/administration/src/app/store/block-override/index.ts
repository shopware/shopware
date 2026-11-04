/**
 * @sw-package framework
 *
 * The `blockOverride` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/blockOverride`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { blockOverrideStoreId, useBlockOverrideStore } from './src/block-override.store';

/** @public */
export type { BlockOverrideStore } from './src/block-override.store';
