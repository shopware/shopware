/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-users-permissions-role-detail needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    appAclService,
    feature as featureService,
    loginService,
    privileges as privilegesService,
    repositoryFactory as repositoryFactoryService,
    ssoSettingsService,
    userService,
} from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;
