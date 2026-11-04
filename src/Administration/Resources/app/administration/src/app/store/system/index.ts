/**
 * @sw-package framework
 *
 * The `system` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/system`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { systemStoreId, useSystemStore } from './src/system.store';

/** @public */
export type { System } from './src/system.store';
