/**
 * @sw-package framework
 *
 * The `error` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/error`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { errorStoreId, useErrorStore } from './src/error.store';

/** @public */
export type { ErrorStore } from './src/error.store';
