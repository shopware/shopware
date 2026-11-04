/**
 * @sw-package checkout
 *
 * Everything sw-promotion-discount-component needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    ruleConditionDataProviderService,
} from 'src/app/service-names';
/** @private */
export { DiscountScopes, DiscountTypes, PromotionPermissions } from 'src/module/sw-promotion-v2/helper/promotion.helper';

/** @private */
export const getApiContext = () => Shopware.Context.api;
