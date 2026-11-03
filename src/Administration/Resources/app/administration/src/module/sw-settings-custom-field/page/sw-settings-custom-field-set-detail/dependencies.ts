/**
 * @sw-package framework
 *
 * Everything sw-settings-custom-field-set-detail needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as discardDetailPageChangesMixin } from 'src/app/mixin/discard-detail-page-changes.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet.mixin';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
