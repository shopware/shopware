/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-cart-condition-form needs from outside its mini-module.
 */
import { swPromotionDetailStoreId } from '../../page/sw-promotion-v2-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export {
    acl as aclService,
    promotionSyncService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export const usePromotionDetailStore = () => Shopware.Store.get(swPromotionDetailStoreId);
