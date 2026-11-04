/**
 * @sw-package checkout
 *
 * Everything sw-promotion-detail-discounts needs from outside its mini-module.
 */

/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { DiscountScopes, DiscountTypes } from 'src/module/sw-promotion-v2/helper/promotion.helper';

/** @private */
export { useSwPromotionDetailStore as usePromotionDetailStore } from '../../page/sw-promotion-v2-detail/store';
