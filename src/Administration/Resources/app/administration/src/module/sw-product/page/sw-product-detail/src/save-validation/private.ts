/**
 * @sw-package inventory
 *
 * The checks and clean-ups a product passes before the page saves it.
 */
/** @private */
export { hasValidPurchaseLimits, normalizeReferencePrices } from './src/save-validation';
/** @private */
export type { ReferencePriceField } from './src/save-validation';
