/**
 * @sw-package inventory
 *
 * Everything sw-product-detail-reviews needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { dateFilterName } from 'src/app/filter/date.filter';
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;
