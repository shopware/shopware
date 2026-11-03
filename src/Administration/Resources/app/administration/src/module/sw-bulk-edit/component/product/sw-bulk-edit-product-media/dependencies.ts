/**
 * @sw-package inventory
 *
 * Everything sw-bulk-edit-product-media needs from outside its mini-module.
 */
import { swProductDetailStoreId } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const useProductDetailStore = () => Shopware.Store.get(swProductDetailStoreId);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
