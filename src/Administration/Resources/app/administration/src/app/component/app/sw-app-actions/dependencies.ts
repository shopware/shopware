/**
 * @sw-package framework
 *
 * Everything sw-app-actions needs from outside its mini-module.
 */
import { userConfigService } from 'src/app/service-names';
import { actionButtonsStoreId } from 'src/app/store/action-buttons';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { createRouter as createRouterVue, createWebHistory } from 'vue-router';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { appActionButtonService, extensionSdkService, feature as featureService } from 'src/app/service-names';

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useActionButtonsStore = () => Shopware.Store.get(actionButtonsStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;

/** @private */
export const hasOwnProperty = ((...args: unknown[]) =>
    (Shopware.Utils.object.hasOwnProperty as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.hasOwnProperty;
