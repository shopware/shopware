/**
 * @sw-package framework
 *
 * The `context` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/context`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { contextStoreId, useContextStore } from './src/context.store';

/** @public */
export type { ContextStore } from './src/context.store';
