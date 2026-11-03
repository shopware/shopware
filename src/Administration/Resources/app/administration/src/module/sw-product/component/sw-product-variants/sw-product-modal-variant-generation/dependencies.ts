/**
 * @sw-package inventory
 *
 * Everything sw-product-modal-variant-generation needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../../page/sw-product-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing.mixin';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export {
    feature as featureService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';
/** @private */
export { default as VariantsGenerator } from '../../../helper/sw-products-variants-generator';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isFeatureActive = (flag: string) => Shopware.Feature.isActive(flag);
