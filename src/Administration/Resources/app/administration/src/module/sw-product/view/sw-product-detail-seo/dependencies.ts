/**
 * @sw-package inventory
 *
 * Everything sw-product-detail-seo needs from outside its mini-module.
 */

/** @private */
export {
    acl as aclService,
    feature as featureService,
    repositoryFactory as repositoryFactoryService,
} from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from '../../page/sw-product-detail/store';
