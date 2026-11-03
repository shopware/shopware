/**
 * @sw-package checkout
 *
 * Everything sw-order-product-select needs from outside its mini-module.
 */
import { repositoryFactory } from 'src/app/service-names';

/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';
/** @private */
export { LineItemType, PriceType } from '../../order.types';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getApiContext = () => Shopware.Context.api;
