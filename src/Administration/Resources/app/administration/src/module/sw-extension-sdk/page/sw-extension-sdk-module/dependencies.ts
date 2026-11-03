/**
 * @sw-package framework
 *
 * Everything sw-extension-sdk-module needs from outside its mini-module.
 */
import { contextStoreId } from 'src/app/store/context.store';
import { extensionSdkModulesStoreId } from 'src/app/store/extension-sdk-module.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useExtensionSdkModulesStore = () => Shopware.Store.get(extensionSdkModulesStoreId);
