/**
 * @sw-package checkout
 *
 * Everything sw-settings-salutation-list needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
