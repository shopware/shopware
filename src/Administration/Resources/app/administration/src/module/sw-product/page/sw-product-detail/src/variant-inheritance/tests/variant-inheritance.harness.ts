/**
 * @sw-package inventory
 */

/** @private */
export { getInheritedTitle, syncVariantPriceInheritance } from '../private';

/**
 * A price list with one price in the default currency.
 *
 * @private
 */
export function createPrices(gross: number) {
    return [{ currencyId: 'default-currency', gross, net: gross, linked: true }];
}
