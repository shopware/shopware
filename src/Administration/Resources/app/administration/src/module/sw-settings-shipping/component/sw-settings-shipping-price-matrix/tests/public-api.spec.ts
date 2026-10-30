import type { PublicInstance } from 'src/core/public-component';
import SwSettingsShippingPriceMatrix from '../index';

/**
 * @sw-package checkout
 *
 * Checks the types of the price matrix's public API. `tsc` reads this file: each `@ts-expect-error` line must
 * fail to compile, every other line must compile.
 */

describe('sw-settings-shipping-price-matrix public API types', () => {
    it('types an override of the price matrix by its public API', () => {
        // ARRANGE
        const override = jest.fn();

        // ACT
        override('sw-settings-shipping-price-matrix', {
            methods: {
                onDeleteShippingPrice(shippingPrice: unknown): void {
                    if (this.disabled) {
                        return;
                    }

                    this.$super('onDeleteShippingPrice', shippingPrice);
                    // @ts-expect-error a typo in a name $super() should call
                    this.$super('onDeleteShippingPrise', shippingPrice);
                },
            },
        } satisfies Parameters<typeof Shopware.Component.override<'sw-settings-shipping-price-matrix'>>[1]);

        // ASSERT
        expect(override).toHaveBeenCalledTimes(1);
    });

    it('types the public instance from the declaration', () => {
        // ARRANGE
        const declaration = SwSettingsShippingPriceMatrix;

        // ACT
        const props: Array<keyof PublicInstance<typeof declaration>> = ['priceGroup', 'disabled'];

        // ASSERT
        expect(declaration.public?.props).toEqual(props);
    });
});
