/**
 * @sw-package framework
 *
 * The `extensionMainModules` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/extensionMainModules`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { extensionMainModulesStoreId, useExtensionMainModulesStore } from './src/main-module.store';

/** @public */
export type { MainModule, ExtensionMainModules } from './src/main-module.store';
