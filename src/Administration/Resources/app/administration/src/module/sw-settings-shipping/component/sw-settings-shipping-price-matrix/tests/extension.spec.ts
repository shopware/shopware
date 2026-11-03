import SwSettingsShippingPriceMatrix from 'shopware:components/sw-settings-shipping-price-matrix';
import type { PublicOverride } from 'shopware:framework/public-component';
import '../register';
import Declaration from '../index';
import {
    deletePrice,
    mountPriceMatrix,
    setUpShippingMethod,
    showAllPrices,
    unmountPriceMatrices,
} from './price-matrix.harness';

/**
 * @sw-package checkout
 *
 * Uses the price matrix the way an extension does: imported as a `shopware:*` module and changed with an override.
 * `tsc` reads this file too, so the override is checked against the public API.
 */

describe('sw-settings-shipping-price-matrix in an extension', () => {
    afterEach(unmountPriceMatrices);

    it('imports the declaration of index.ts', () => {
        // ASSERT
        expect(SwSettingsShippingPriceMatrix).toBe(Declaration);
    });

    it('overrides a public method, which still reaches the original through $super', async () => {
        // ARRANGE
        const remove = setUpShippingMethod();
        const deleted: unknown[] = [];

        Shopware.Component.override(SwSettingsShippingPriceMatrix.name, {
            methods: {
                onDeleteShippingPrice(shippingPrice: unknown): void {
                    deleted.push(shippingPrice);
                    this.$super('onDeleteShippingPrice', shippingPrice);
                },
            },
        });

        const wrapper = await mountPriceMatrix();
        await showAllPrices(wrapper);

        // ACT
        await deletePrice(wrapper, 1);

        // ASSERT
        expect(deleted).toEqual([expect.objectContaining({ id: 'priceId2' })]);
        expect(remove).toHaveBeenCalledWith('priceId2');
    });

    it('checks an override against the public API', () => {
        // ACT
        const override: PublicOverride<typeof SwSettingsShippingPriceMatrix> = {
            methods: {
                onDeleteShippingPrice(shippingPrice: unknown): void {
                    // @ts-expect-error $super() takes the name of a public method only
                    this.$super('onDeleteShippingPrise', shippingPrice);
                },
                // @ts-expect-error a public method keeps its signature
                getPrice: 'not a method',
            },
        };

        // ASSERT
        expect(override.methods).toHaveProperty('onDeleteShippingPrice');
    });
});
