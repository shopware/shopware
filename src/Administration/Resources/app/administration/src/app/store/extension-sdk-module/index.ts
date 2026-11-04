/**
 * @sw-package framework
 *
 * The `extensionSdkModules` Pinia store of the Administration.
 * This file is the public API, also for extensions, which reach the store as `shopware:stores/extensionSdkModules`.
 */
/**
 * @public
 * @shopware-kind stores
 */
export { extensionSdkModulesStoreId, useExtensionSdkModulesStore } from './src/extension-sdk-module.store';

/** @public */
export type { ExtensionSdkModule, ExtensionSdkModules } from './src/extension-sdk-module.store';
