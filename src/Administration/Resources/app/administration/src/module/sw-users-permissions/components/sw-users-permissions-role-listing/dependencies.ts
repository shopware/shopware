/**
 * @sw-package fundamentals@framework
 *
 * Everything sw-users-permissions-role-listing needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService, ssoSettingsService } from 'src/app/service-names';

/** @private */
export const getApiContext = () => Shopware.Context.api;
