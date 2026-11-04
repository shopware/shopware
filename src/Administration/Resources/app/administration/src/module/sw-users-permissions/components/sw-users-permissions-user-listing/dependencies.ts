/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-users-permissions-user-listing needs from outside its mini-module.
 */
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as salutationMixin } from 'src/app/mixin/salutation';
/** @private */
export {
    acl as aclService,
    loginService,
    repositoryFactory as repositoryFactoryService,
    ssoSettingsService,
    userService,
} from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
