import {
    BUTTON_DELETE_PRICE,
    BUTTON_SHOW_ALL_PRICES,
    ROW_PRICE,
    cloneDeep,
    createQuantityPrices,
    deletePrice,
    mountPriceMatrix,
    setUpShippingMethod,
    showAllPrices,
    toRulePrices,
    unmountPriceMatrices,
} from './price-matrix.harness';

/**
 * @sw-package checkout
 */

describe('sw-settings-shipping-price-matrix', () => {
    let remove: jest.Mock;

    beforeEach(() => {
        remove = setUpShippingMethod();
    });

    afterEach(unmountPriceMatrices);

    it('shows only the first price at first', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix();

        // ACT
        const rows = wrapper.findAll(ROW_PRICE);

        // ASSERT
        expect(rows).toHaveLength(1);
    });

    it('shows all prices when asked, and hides the button that asks', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix();

        // ACT
        await showAllPrices(wrapper);

        // ASSERT
        expect(wrapper.findAll(ROW_PRICE)).toHaveLength(2);
        expect(wrapper.find(BUTTON_SHOW_ALL_PRICES).exists()).toBe(false);
    });

    it.each([
        [0, [[1, null]]],
        [1, [[1, null]]],
    ])('keeps the quantity ranges contiguous when deleting price %s', async (row, expectedRanges) => {
        // ARRANGE
        const prices = createQuantityPrices();
        const wrapper = await mountPriceMatrix({ prices });
        await showAllPrices(wrapper);

        // ACT
        await deletePrice(wrapper, row);

        // ASSERT
        expect(remove).toHaveBeenCalledWith(prices[row].id);
        expect(
            prices.filter((price, index) => index !== row).map((price) => [price.quantityStart, price.quantityEnd]),
        ).toEqual(expectedRanges);
    });

    it('deletes the second rule price without changing the other rule prices', async () => {
        // ARRANGE
        const prices = toRulePrices(createQuantityPrices());
        const wrapper = await mountPriceMatrix({ calculation: null, prices });
        const untouched = cloneDeep(prices);
        await showAllPrices(wrapper);

        // ACT
        await deletePrice(wrapper, 1);

        // ASSERT
        expect(remove).toHaveBeenCalledWith('priceId2');
        expect(prices).toEqual(untouched);
    });

    it('disables the delete button of the last price', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix({ prices: [createQuantityPrices()[0]] });

        // ACT
        const deleteButton = wrapper.find(ROW_PRICE).find(BUTTON_DELETE_PRICE);

        // ASSERT
        expect(deleteButton.attributes('disabled')).toBeDefined();
    });
});
