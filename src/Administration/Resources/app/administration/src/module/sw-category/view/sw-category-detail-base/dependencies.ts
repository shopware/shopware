/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-base needs from outside its mini-module.
 */
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);
