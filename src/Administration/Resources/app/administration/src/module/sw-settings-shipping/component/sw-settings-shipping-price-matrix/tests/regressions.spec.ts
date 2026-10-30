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

    /**
     * @regression https://github.com/shopware/shopware/pull/22132
     * Deleting the first price of a matrix that calculates by rule failed with
     * "can't access property quantityEnd", and the price stayed.
     * The delete handler adjusted the quantity range of the price before it, which rule prices don't have.
     */
    it.each([false, true])('deletes the first rule price, with all prices shown: %s', async (showAll) => {
        const prices = toRulePrices(createQuantityPrices());
        const wrapper = await mountPriceMatrix({ calculation: null, prices });
        const untouched = Shopware.Utils.object.cloneDeep(prices);

        if (showAll) {
            await showAllPrices(wrapper);
        }
        await deletePrice(wrapper, 0);

        expect(remove).toHaveBeenCalledWith('priceId1');
        expect(prices).toEqual(untouched);
    });
});
