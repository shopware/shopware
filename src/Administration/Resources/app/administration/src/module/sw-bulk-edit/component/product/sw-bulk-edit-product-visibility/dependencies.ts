/**
 * @sw-package inventory
 *
 * Everything the bulk edit product visibility needs from outside its mini-module.
 */
import { swProductDetailStoreId } from '../../../../sw-product/page/sw-product-detail/store';

/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** The product being bulk edited, shared with the product detail store. @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;
