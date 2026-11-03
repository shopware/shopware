/**
 * @sw-package inventory
 *
 * Everything sw-product-variants-delivery-listing needs from outside its mini-module.
 */
/** @private */
export { default as Criteria } from 'src/core/data/criteria.data';

/** @private */
export const getApiContext = () => Shopware.Context.api;
