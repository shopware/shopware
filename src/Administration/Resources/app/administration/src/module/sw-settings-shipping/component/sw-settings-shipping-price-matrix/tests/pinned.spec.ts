import {
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
        const wrapper = await mountPriceMatrix();

        const rows = wrapper.findAll('tbody .sw-data-grid__row');

        expect(rows).toHaveLength(1);
    });

    it('shows all prices when asked, and hides the button that asks', async () => {
        const wrapper = await mountPriceMatrix();

        await showAllPrices(wrapper);

        expect(wrapper.findAll('tbody .sw-data-grid__row')).toHaveLength(2);
        expect(wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').exists()).toBe(false);
    });

    it.each([
        [0, [[1, null]]],
        [1, [[1, null]]],
    ])('keeps the quantity ranges contiguous when deleting price %s', async (row, expectedRanges) => {
        const prices = createQuantityPrices();
        const wrapper = await mountPriceMatrix({ prices });
        await showAllPrices(wrapper);

        await deletePrice(wrapper, row);

        expect(remove).toHaveBeenCalledWith(prices[row].id);
        expect(
            prices.filter((price, index) => index !== row).map((price) => [price.quantityStart, price.quantityEnd]),
        ).toEqual(expectedRanges);
    });

    it('deletes the second rule price without changing the other rule prices', async () => {
        const prices = toRulePrices(createQuantityPrices());
        const wrapper = await mountPriceMatrix({ calculation: null, prices });
        const untouched = Shopware.Utils.object.cloneDeep(prices);
        await showAllPrices(wrapper);

        await deletePrice(wrapper, 1);

        expect(remove).toHaveBeenCalledWith('priceId2');
        expect(prices).toEqual(untouched);
    });

    it('disables the delete button of the last price', async () => {
        const wrapper = await mountPriceMatrix({ prices: [createQuantityPrices()[0]] });

        const deleteButton = wrapper.find('tbody .sw-data-grid__row button');

        expect(deleteButton.attributes('disabled')).toBeDefined();
    });
});
