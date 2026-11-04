/**
 * @sw-package framework
 *
 * The `extensionEntryRoutes` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/extensionEntryRoutes`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { extensionEntryRoutesStoreId, useExtensionEntryRoutesStore } from './src/extension-entry-routes.store';

/** @public */
export type { ExtensionEntryRoutes } from './src/extension-entry-routes.store';
