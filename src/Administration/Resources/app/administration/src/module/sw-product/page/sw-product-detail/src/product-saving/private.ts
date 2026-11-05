/**
 * @sw-package inventory
 *
 * How the product detail page checks and saves a product, and what it tells the merchant when that fails.
 */
/** @private */
export { useProductSaving } from './src/product-saving';
/** @private */
export type {
    ProductSaving,
    ProductSavingDependencies,
    ProductSavingState,
    SavedProduct,
    SaveResult,
} from './src/product-saving';
