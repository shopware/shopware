/**
 * @sw-package checkout
 *
 * Everything sw-extension-app-module-page needs from outside its mini-module.
 */
import { extensionSdkModulesStoreId } from 'src/app/store/extension-sdk-module.store';
import { sessionStoreId } from 'src/app/store/session.store';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';
/** @private */
export { acl as aclService, extensionSdkService } from 'src/app/service-names';

/** @private */
export const useExtensionSdkModulesStore = () => Shopware.Store.get(extensionSdkModulesStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
