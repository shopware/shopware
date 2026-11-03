/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-profile-index needs from outside its mini-module.
 */
import { localeHelper, timezoneService, userConfigService } from 'src/app/service-names';
import { errorStoreId } from 'src/app/store/error.store';
import { sessionStoreId } from 'src/app/store/session.store';
import { swProfileStoreId } from '../../store/sw-profile.store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    loginService,
    mediaDefaultFolderService,
    repositoryFactory as repositoryFactoryService,
    searchPreferencesService,
    searchRankingService,
    ssoSettingsService,
    userService,
    validationApiService,
} from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const getLocaleHelper = (): ServiceContainer['localeHelper'] => Shopware.Service(localeHelper);

/** @private */
export const getTimezoneService = (): ServiceContainer['timezoneService'] => Shopware.Service(timezoneService);

/** @private */
export const getUserConfigService = () => Shopware.Service(userConfigService);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const useProfileStore = () => Shopware.Store.get(swProfileStoreId);

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAppContext = () => Shopware.Context.app;
