import {
    BUTTON_ADD_PRICE,
    createQuantityPrices,
    deletePrice,
    getShippingMethodPrices,
    mountPriceMatrix,
    setUpShippingMethod,
    showAllPrices,
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
     * @requirement A merchant can add a price to a price matrix.
     * Each price covers a quantity range or a rule, so a matrix needs as many prices as the shipping costs have steps.
     */
    it('adds a price', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix();

        // ACT
        await wrapper.find(BUTTON_ADD_PRICE).trigger('click');

        // ASSERT
        expect(getShippingMethodPrices()).toHaveLength(1);
    });

    /**
     * @requirement A merchant can delete a price from a price matrix.
     */
    it('deletes a price', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix();
        await showAllPrices(wrapper);

        // ACT
        await deletePrice(wrapper, 1);

        // ASSERT
        expect(remove).toHaveBeenCalledWith('priceId2');
    });

    /**
     * @requirement A price matrix always keeps at least one price.
     * A matrix without prices would make the shipping method free for everything the matrix's rule covers.
     */
    it('keeps the last price', async () => {
        // ARRANGE
        const wrapper = await mountPriceMatrix({ prices: [createQuantityPrices()[0]] });

        // ACT
        await deletePrice(wrapper, 0);

        // ASSERT
        expect(remove).not.toHaveBeenCalled();
    });
});
