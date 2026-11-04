import { createPrices, getInheritedTitle, syncVariantPriceInheritance } from './variant-inheritance.harness';

/**
 * @sw-package inventory
 */

// A variant on the product detail page shows what it inherits from its parent product until the merchant overrides it.
// Its price and purchase prices inherit together, and a variant without a name of its own shows the name of its parent.
describe('what a variant inherits from its parent', () => {
    /**
     * @requirement A merchant who lets a variant inherit its price lets it inherit its purchase prices too.
     */
    it('inherits the purchase prices together with the price', () => {
        // ARRANGE
        const variant = { price: null, purchasePrices: createPrices(5) };

        // ACT
        syncVariantPriceInheritance(variant, { purchasePrices: createPrices(3) });

        // ASSERT
        expect(variant.purchasePrices).toBeNull();
    });

    /**
     * @requirement A merchant who gives a variant its own price starts from the parent's purchase prices.
     */
    it('copies the parent purchase prices when the variant has its own price', () => {
        // ARRANGE
        const parent = { purchasePrices: createPrices(3) };
        const variant = { price: createPrices(10), purchasePrices: null };

        // ACT
        syncVariantPriceInheritance(variant, parent);

        // ASSERT
        expect(variant.purchasePrices).toEqual(parent.purchasePrices);
        expect(variant.purchasePrices).not.toBe(parent.purchasePrices);
    });

    /**
     * @requirement A merchant keeps the purchase prices they gave a variant.
     */
    it('keeps purchase prices the variant has of its own', () => {
        // ARRANGE
        const own = createPrices(4);
        const variant = { price: createPrices(10), purchasePrices: own };

        // ACT
        syncVariantPriceInheritance(variant, { purchasePrices: createPrices(3) });

        // ASSERT
        expect(variant.purchasePrices).toBe(own);
    });

    /**
     * @requirement A merchant sees a variant under its own name, in the language they edit.
     */
    it('titles a variant with its own translated name', () => {
        // ACT
        const title = getInheritedTitle({ name: 'Shirt red', translated: { name: 'Hemd rot' } }, { name: 'Shirt' });

        // ASSERT
        expect(title).toBe('Hemd rot');
    });

    /**
     * @requirement A merchant sees a variant without a name of its own under the name of its parent.
     */
    it('titles a variant without a name with the name of its parent', () => {
        // ACT
        const title = getInheritedTitle(
            { name: null, translated: { name: null } },
            { name: 'Shirt', translated: { name: 'Hemd' } },
        );

        // ASSERT
        expect(title).toBe('Hemd');
    });

    /**
     * @requirement A merchant sees an empty title rather than an error while the parent is not loaded yet.
     */
    it('titles a variant without names on either side with an empty string', () => {
        // ACT
        const title = getInheritedTitle({ name: null, translated: { name: null } }, {});

        // ASSERT
        expect(title).toBe('');
    });
});
