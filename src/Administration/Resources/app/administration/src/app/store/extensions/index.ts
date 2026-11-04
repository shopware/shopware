/**
 * @sw-package framework
 *
 * The `extensions` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/extensions`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { extensionsStoreId, useExtensionsStore } from './src/extensions.store';

/** @public */
export type { Extension, ExtensionsState, Extensions } from './src/extensions.store';
