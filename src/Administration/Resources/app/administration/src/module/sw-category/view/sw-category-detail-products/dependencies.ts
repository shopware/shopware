/**
 * @sw-package discovery
 *
 * Everything sw-category-detail-products needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { swCategoryDetailStoreId } from '../../page/sw-category-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';

/** @private */
export const useCategoryDetailStore = () => Shopware.Store.get(swCategoryDetailStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
