import { cloneDeep } from '../dependencies';

/**
 * @sw-package inventory
 */

type PricedProduct = { price?: unknown[] | null; purchasePrices?: unknown[] | null };

type NamedProduct = { name?: string | null; translated?: { name?: string | null } };

const hasOwn = (object: object, key: string) => Object.prototype.hasOwnProperty.call(object, key);

/**
 * Keeps the purchase prices of a variant in step with its price, because the API stores them together.
 * A variant that inherits its price inherits its purchase prices too; one with its own price starts from a copy of the parent's purchase prices.
 * Changes the variant in place.
 *
 * @example
 * syncVariantPriceInheritance(variant, parent); // variant.price === null → variant.purchasePrices === null
 *
 * @private
 */
export function syncVariantPriceInheritance(variant: PricedProduct, parent: PricedProduct): void {
    if (variant.price === null) {
        variant.purchasePrices = null;
        return;
    }

    if (variant.purchasePrices === null) {
        variant.purchasePrices = cloneDeep(parent.purchasePrices);
    }
}

/**
 * The title of a variant: its own translated or plain name, else the name of its parent, else an empty string.
 *
 * @example
 * getInheritedTitle({ name: null, translated: { name: null } }, { name: 'Shirt', translated: { name: 'Shirt' } }); // → 'Shirt'
 *
 * @private
 */
export function getInheritedTitle(variant: NamedProduct, parent: NamedProduct | null | undefined): string {
    const { translated } = variant;
    if (hasOwn(variant, 'translated') && translated && hasOwn(translated, 'name') && translated.name !== null) {
        return translated.name as string;
    }

    if (variant.name !== null) {
        return variant.name as string;
    }

    if (parent && hasOwn(parent, 'translated')) {
        return (parent.translated && hasOwn(parent.translated, 'name') ? parent.translated.name : parent.name) as string;
    }

    return '';
}
