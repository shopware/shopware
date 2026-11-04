/**
 * @sw-package discovery
 *
 * Everything sw-settings-customer-group-list needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
