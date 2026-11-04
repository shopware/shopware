/**
 * @sw-package inventory
 */

/**
 * The reference prices a product price may carry next to its own gross and net price.
 *
 * @private
 */
export type ReferencePriceField = 'listPrice' | 'regulationPrice';

type ReferencePrice = { gross?: number | null; net?: number | null };

type PriceWithReferences = Partial<Record<ReferencePriceField, ReferencePrice | null>>;

/**
 * Whether a customer can buy the product at all: the minimum purchase must not exceed a set maximum purchase.
 *
 * @example
 * hasValidPurchaseLimits({ minPurchase: 5, maxPurchase: 2 }); // → false
 *
 * @private
 */
export function hasValidPurchaseLimits(product: { minPurchase?: number | null; maxPurchase?: number | null }): boolean {
    if (product.maxPurchase && Number(product.minPurchase) > product.maxPurchase) {
        return false;
    }

    return true;
}

/**
 * Clears a reference price that has neither gross nor net, and fills a missing side of one that has a value with 0,
 * so the API stores either no reference price or a complete one. Changes the prices in place.
 *
 * @example
 * const price = [{ listPrice: { gross: 10, net: null } }];
 * normalizeReferencePrices(price, 'listPrice'); // price[0].listPrice → { gross: 10, net: 0 }
 *
 * @private
 */
export function normalizeReferencePrices(
    prices: PriceWithReferences[] | null | undefined,
    field: ReferencePriceField,
): void {
    if (!prices) {
        return;
    }

    prices.forEach((price) => {
        const referencePrice = price[field];

        if (!referencePrice) {
            return;
        }

        if (!referencePrice.gross && !referencePrice.net) {
            price[field] = null;
            return;
        }

        if (!referencePrice.gross) {
            referencePrice.gross = 0;
            return;
        }

        if (!referencePrice.net) {
            referencePrice.net = 0;
        }
    });
}
