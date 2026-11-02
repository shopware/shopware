import {
    BUTTON_ADD_PRICE_MATRIX,
    PRICE_MATRIX,
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

    it('adds a price without a rule that starts at quantity 0 to the shipping method', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', 'ruleA')]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await addPriceMatrix(wrapper);

        // ASSERT
        expect(getShippingMethodPrices()[1]).toEqual({
            shippingMethodId: 'shippingMethodId',
            quantityStart: 0,
            ruleId: null,
        });
    });

    it('flags an added price as a new matrix when a matrix without a rule exists', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', null)]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await addPriceMatrix(wrapper);

        // ASSERT
        expect(getShippingMethodPrices()[1]._inNewMatrix).toBe(true);
        expect(getShownPriceGroups(wrapper).map((group) => group.isNew)).toEqual([false, true]);
    });

    it('enables the add button while no new matrix exists', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', null)]);

        // ACT
        const wrapper = await mountPriceMatrices();

        // ASSERT
        expect(wrapper.find(BUTTON_ADD_PRICE_MATRIX).attributes('disabled')).toBeUndefined();
    });

    it('copies the calculation, quantities and a deep copy of the currency prices when duplicating', async () => {
        // ARRANGE
        const original = createPrice('price1', 'ruleA', {
            calculation: 2,
            calculationRule: { id: 'calculationRuleId' },
            calculationRuleId: 'calculationRuleId',
            quantityStart: 3,
            quantityEnd: 7,
        });
        setUpShippingMethod([original]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await duplicatePriceMatrix(wrapper, 0);

        // ASSERT
        const duplicate = getShippingMethodPrices()[1];
        expect(duplicate).toEqual({
            ruleId: null,
            calculation: 2,
            calculationRule: { id: 'calculationRuleId' },
            calculationRuleId: 'calculationRuleId',
            shippingMethodId: 'shippingMethodId',
            currencyPrice: original.currencyPrice,
            quantityStart: 3,
            quantityEnd: 7,
        });
        expect(duplicate.currencyPrice).not.toBe(original.currencyPrice);
    });

    it('flags duplicated prices as a new matrix when a matrix without a rule exists', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', null), createPrice('price2', 'ruleA')]);
        const wrapper = await mountPriceMatrices();

        // ACT
        await duplicatePriceMatrix(wrapper, 1);

        // ASSERT
        expect(getShippingMethodPrices()[2]._inNewMatrix).toBe(true);
    });

    it('deletes only the flagged prices when deleting the new matrix', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', null), createPrice('price2', 'ruleA')]);
        const wrapper = await mountPriceMatrices();
        await addPriceMatrix(wrapper);

        // ACT
        await deletePriceMatrix(wrapper, 2);

        // ASSERT
        expect(getShippingMethodPrices().map((price) => price.id)).toEqual(['price1', 'price2']);
    });

    it('passes disabled to the price matrices and disables the add button', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', 'ruleA')]);

        // ACT
        const wrapper = await mountPriceMatrices({ disabled: true });

        // ASSERT
        expect(wrapper.findComponent({ name: PRICE_MATRIX }).props('disabled')).toBe(true);
        expect(wrapper.find(BUTTON_ADD_PRICE_MATRIX).attributes('disabled')).toBeDefined();
    });

    it('passes no disabled to the price matrices when not disabled', async () => {
        // ARRANGE
        setUpShippingMethod([createPrice('price1', 'ruleA')]);

        // ACT
        const wrapper = await mountPriceMatrices();

        // ASSERT
        expect(wrapper.findComponent({ name: PRICE_MATRIX }).props('disabled')).toBeUndefined();
    });
});
