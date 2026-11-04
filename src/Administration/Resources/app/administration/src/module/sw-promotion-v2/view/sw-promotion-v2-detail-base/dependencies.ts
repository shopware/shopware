/**
 * @sw-package checkout
 *
 * Everything sw-promotion-v2-detail-base needs from outside its mini-module.
 */
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder';
/** @private */
export { acl as aclService, customFieldDataProviderService, promotionCodeApiService } from 'src/app/service-names';
/** @private */
export { mapPropertyErrors } from 'src/app/service/map-errors.service';
