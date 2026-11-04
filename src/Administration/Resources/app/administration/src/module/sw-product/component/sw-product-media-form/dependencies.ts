/**
 * @sw-package inventory
 *
 * Everything sw-product-media-form needs from outside its mini-module.
 */

/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export {
    acl as aclService,
    repositoryFactory as repositoryFactoryService,
    systemConfigApiService,
} from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';
