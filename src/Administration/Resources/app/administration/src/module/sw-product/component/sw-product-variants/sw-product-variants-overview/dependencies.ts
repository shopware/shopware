/**
 * @sw-package inventory
 *
 * Everything sw-product-variants-overview needs from outside its mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as listingMixin } from 'src/app/mixin/listing';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export {
    acl as aclService,
    feature as featureService,
    mediaService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../../page/sw-product-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;
