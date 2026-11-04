/**
 * @sw-package inventory
 *
 * How the product detail page loads a product, its variant parent and the shop data around it into its store.
 */
/** @private */
export { useProductLoading } from './src/product-loading';
/** @private */
export type { LoadedProduct, ProductLoading, ProductLoadingPage, PurchasePrice } from './src/product-loading';
/** @private */
export { createDefaultFeatureSetCriteria, createProductCriteria } from './src/product-criteria';
