/**
 * @sw-package inventory
 *
 * Everything sw-product-variant-modal needs from outside its mini-module.
 */
import { stockColorVariantFilterName } from 'src/app/filter/stock-color-variant.filter';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { acl as aclService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';

/** @private */
export const getStockColorVariantFilter = () => Shopware.Filter.getByName(stockColorVariantFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const getAppContext = () => Shopware.Context.app;
