/**
 * @sw-package framework
 *
 * Everything sw-admin-menu needs from outside its mini-module.
 */
import { adminMenuStoreId } from 'src/app/store/admin-menu';
import { menuItemStoreId } from 'src/app/store/menu-item';
import { notificationStoreId } from 'src/app/store/notification';
import { sessionStoreId } from 'src/app/store/session';
import { shopwareAppsStoreId } from 'src/app/store/shopware-apps';

/** @private */
export { createFocusTrap } from 'focus-trap';
/** @private */
export { default as FlatTreeHelper } from 'src/core/helper/flattree.helper';
/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { default as useModuleIconColors } from 'src/app/composables/use-module-icon-colors';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    appModulesService,
    customEntityDefinitionService,
    feature as featureService,
    loginService,
    menuService,
    systemConfigApiService,
    userService,
} from 'src/app/service-names';
/** @private */
export { getActiveRouteNames, isEntryOnActiveRoute } from '../sw-admin-menu-item/menu-item-active.helper';

/** @private */
export const useAdminMenuStore = () => Shopware.Store.get(adminMenuStoreId);

/** @private */
export const useMenuItemStore = () => Shopware.Store.get(menuItemStoreId);

/** @private */
export const useNotificationStore = () => Shopware.Store.get(notificationStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const useShopwareAppsStore = () => Shopware.Store.get(shopwareAppsStoreId);

/** @private */
export const createId = ((...args: unknown[]) =>
    (Shopware.Utils.createId as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.createId;

/** @private */
export const error = ((...args: unknown[]) =>
    (Shopware.Utils.debug.error as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.error;

/** @private */
export const getScrollbarWidth = ((...args: unknown[]) =>
    (Shopware.Utils.dom.getScrollbarWidth as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.dom.getScrollbarWidth;
