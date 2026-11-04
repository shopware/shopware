/**
 * @sw-package framework
 *
 * The `session` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/session`.
 */
/**
 * @public
 * @export-namespace stores
 */
export { sessionStoreId, useSessionStore } from './src/session.store';

/** @public */
export type { SessionStore } from './src/session.store';
