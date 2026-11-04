/**
 * @sw-package framework
 *
 * Everything sw-custom-field-list needs from outside its mini-module.
 */
import { cacheService } from 'src/app/service-names';
import { errorStoreId } from 'src/app/store/error';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { default as swInlineSnippetMixin } from 'src/app/mixin/sw-inline-snippet';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getCacheService = () => Shopware.Service(cacheService);

/** @private */
export const useErrorStore = () => Shopware.Store.get(errorStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
