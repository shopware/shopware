/**
 * @sw-package discovery
 *
 * Everything sw-landing-page-tree needs from outside its mini-module.
 */
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService, syncService } from 'src/app/service-names';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
