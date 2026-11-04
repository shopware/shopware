/**
 * @sw-package inventory
 *
 * Everything sw-bulk-edit-product-media needs from outside its mini-module.
 */

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export { useSwProductDetailStore as useProductDetailStore } from 'src/module/sw-product/page/sw-product-detail/store';

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const isEmpty = ((...args: unknown[]) =>
    (Shopware.Utils.types.isEmpty as (...args: unknown[]) => unknown)(...args)) as typeof Shopware.Utils.types.isEmpty;
