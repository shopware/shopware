import { hasValidPurchaseLimits, normalizeReferencePrices } from '../private';

/**
 * @sw-package inventory
 */

// Before the product detail page saves a product, it checks that customers can buy it and tidies up its reference prices.
// A merchant who sets a list or regulation price on one side only gets the other side as 0, and an empty one is removed.
describe('the checks before a product is saved', () => {
    /**
     * @requirement A merchant can save a product whose minimum purchase fits into its maximum purchase.
     */
    it('accepts purchase limits a customer can meet', () => {
        // ACT
        const valid = hasValidPurchaseLimits({ minPurchase: 2, maxPurchase: 5 });

        // ASSERT
        expect(valid).toBe(true);
    });

    /**
     * @requirement A merchant cannot save a product nobody could buy, because its minimum purchase exceeds its maximum.
     */
    it('rejects a minimum purchase above the maximum purchase', () => {
        // ACT
        const valid = hasValidPurchaseLimits({ minPurchase: 6, maxPurchase: 5 });

        // ASSERT
        expect(valid).toBe(false);
    });

    /**
     * @requirement A merchant without a maximum purchase can set any minimum purchase.
     */
    it('accepts any minimum purchase when there is no maximum', () => {
        // ACT
        const valid = hasValidPurchaseLimits({ minPurchase: 100, maxPurchase: null });

        // ASSERT
        expect(valid).toBe(true);
    });

    /**
     * @requirement A merchant who enters a reference price on one side gets the other side stored as 0.
     */
    it('fills the missing side of a reference price with 0', () => {
        // ARRANGE
        const prices = [
            { listPrice: { gross: 10, net: null } },
            { listPrice: { gross: null, net: 8 } },
        ];

        // ACT
        normalizeReferencePrices(prices, 'listPrice');

        // ASSERT
        expect(prices).toEqual([
            { listPrice: { gross: 10, net: 0 } },
            { listPrice: { gross: 0, net: 8 } },
        ]);
    });

    /**
     * @requirement A merchant who leaves a reference price empty saves the product without one.
     */
    it('removes a reference price without gross and net', () => {
        // ARRANGE
        const prices = [{ regulationPrice: { gross: 0, net: 0 }, listPrice: { gross: 5, net: 4 } }];

        // ACT
        normalizeReferencePrices(prices, 'regulationPrice');

        // ASSERT
        expect(prices).toEqual([{ regulationPrice: null, listPrice: { gross: 5, net: 4 } }]);
    });
});
