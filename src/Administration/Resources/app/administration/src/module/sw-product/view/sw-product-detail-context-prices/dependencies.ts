/**
 * @sw-package inventory
 *
 * Everything sw-product-detail-context-prices needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { swProductDetailStoreId } from '../../page/sw-product-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
