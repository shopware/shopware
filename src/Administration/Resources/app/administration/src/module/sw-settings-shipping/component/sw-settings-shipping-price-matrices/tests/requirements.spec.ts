import {
    BUTTON_ADD_PRICE_MATRIX,
    addPriceMatrix,
    createPrice,
    deletePriceMatrix,
    duplicatePriceMatrix,
    getShippingMethodPrices,
    getShownPriceGroups,
    mountPriceMatrices,
    setUpShippingMethod,
    unmountPriceMatrices,
} from './price-matrices.harness';

/**
 * @sw-package checkout
 */

describe('sw-settings-shipping-price-matrices', () => {
    afterEach(unmountPriceMatrices);

    /**
     * @requirement A merchant sees one price matrix per rule of a shipping method.
     * The rule decides when its prices apply, so the prices of one rule belong together.
     */
    it('shows one price matrix per rule', async () => {
        // ARRANGE
        setUpShippingMethod([
            createPrice('price1', 'ruleA'),
            createPrice('price2', 'ruleA', { quantityStart: 2 }),
            createPrice('price3', 'ruleB'),
        ]);

        // ACT
        const wrapper = await mountPriceMatrices();

        // ASSERT
        expect(getShownPriceGroups(wrapper).map((group) => group.ruleId)).toEqual(['ruleA', 'ruleB']);
    });

    /**
     * @requirement A merchant can add a price matrix, which starts without a rule.
     * A shipping method needs a matrix per rule its costs depend on.
     */
    it('adds a price matrix', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', 'ruleA')]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await addPriceMatrix(wrapper);

        // ASSERT
        expect(getShippingMethodPrices()).toHaveLength(2);
        expect(getShownPriceGroups(wrapper)).toHaveLength(2);
    });

    /**
     * @requirement A merchant can duplicate a price matrix, which copies its prices into a new matrix without a rule.
     * Prices for a second rule often differ only a little from the first.
     */
    it('duplicates a price matrix', async () => {
        // ARRANGE
        setUpShippingMethod([
            createPrice('price1', 'ruleA'),
            createPrice('price2', 'ruleA', { quantityStart: 2 }),
        ]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await duplicatePriceMatrix(wrapper, 0);

        // ASSERT
        expect(getShippingMethodPrices().filter((price) => price.ruleId === null)).toHaveLength(2);
    });

    /**
     * @requirement A merchant can delete a price matrix with all its prices.
     */
    it('deletes a price matrix', async () => {
        // ARRANGE
        setUpShippingMethod([
            createPrice('price1', 'ruleA'),
            createPrice('price2', 'ruleA', { quantityStart: 2 }),
            createPrice('price3', 'ruleB'),
        ]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await deletePriceMatrix(wrapper, 0);

        // ASSERT
        expect(getShippingMethodPrices().map((price) => price.id)).toEqual(['price3']);
    });

    /**
     * @requirement A merchant adds one new price matrix at a time.
     * The new matrix has no rule yet, and a second one without a rule could not be told apart from it.
     */
    it('lets a merchant add only one new price matrix at a time', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', null)]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await addPriceMatrix(wrapper);

        // ASSERT
        expect(wrapper.find(BUTTON_ADD_PRICE_MATRIX).attributes('disabled')).toBeDefined();
    });
});
