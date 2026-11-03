/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-users-permissions-user-detail needs from outside its mini-module.
 */
import { localeHelper, timezoneService } from 'src/app/service-names';
import { contextStoreId } from 'src/app/store/context.store';
import { errorStoreId } from 'src/app/store/error.store';
import { sessionStoreId } from 'src/app/store/session.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as useModuleIconColors } from 'src/app/composables/use-module-icon-colors';
/** @private */
export { default as useTheme } from 'src/app/composables/use-theme';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    integrationService,
    loginService,
    mediaDefaultFolderService,
    repositoryFactory as repositoryFactoryService,
    userService,
    userValidationService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getLocaleHelper = (): ServiceContainer['localeHelper'] => Shopware.Service(localeHelper);

/** @private */
export const getTimezoneService = (): ServiceContainer['timezoneService'] => Shopware.Service(timezoneService);

/** @private */
export const useContextStore = () => Shopware.Store.get(contextStoreId);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const publishData = ((...args: unknown[]) =>
    (Shopware.ExtensionAPI.publishData as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.ExtensionAPI.publishData;

/** @private */
export const warn = ((...args: unknown[]) =>
    (Shopware.Utils.debug.warn as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.debug.warn;
