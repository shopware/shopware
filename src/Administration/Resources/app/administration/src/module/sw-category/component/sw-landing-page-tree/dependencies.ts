/**
 * @sw-package discovery
 *
 * Everything sw-landing-page-tree needs from outside its mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService, syncService } from 'src/app/service-names';

/** @private */
export { useSwCategoryDetailStore as useCategoryDetailStore } from '../../page/sw-category-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;
