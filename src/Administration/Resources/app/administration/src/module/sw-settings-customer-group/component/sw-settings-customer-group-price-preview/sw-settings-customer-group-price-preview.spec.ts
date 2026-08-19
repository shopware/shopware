/**
 * @sw-package discovery
 */

import { mount } from '@vue/test-utils';

async function createWrapper(mode: string) {
    return mount(
        await wrapTestComponent('sw-settings-customer-group-price-preview', {
            sync: true,
        }),
        {
            props: {
                mode,
            },
        },
    );
}

describe('src/module/sw-settings-customer-group/component/sw-settings-customer-group-price-preview', () => {
    beforeEach(() => {
        Shopware.Context.app.systemCurrencyISOCode = 'EUR';
    });

    it('should render one panel per tax rate', async () => {
        const wrapper = await createWrapper('gross');

        const countries = wrapper.findAll('.sw-settings-customer-group-price-preview__country');

        expect(countries).toHaveLength(2);
        expect(countries.at(0)?.find('.sw-settings-customer-group-price-preview__country-name').text()).toBe(
            'sw-settings-customer-group.priceDisplay.preview.countryA',
        );
        expect(countries.at(1)?.find('.sw-settings-customer-group-price-preview__country-name').text()).toBe(
            'sw-settings-customer-group.priceDisplay.preview.countryB',
        );
    });

    it('should keep the gross price fixed and let the merchant proceeds vary', async () => {
        const wrapper = await createWrapper('gross');

        expect(wrapper.vm.examples).toEqual([
            {
                key: 'tax-rate-19',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryA',
                taxRate: 19,
                displayedPrice: 11.9,
                taxAmount: 1.9,
                totalPrice: 11.9,
                merchantPrice: 10,
            },
            {
                key: 'tax-rate-20',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryB',
                taxRate: 20,
                displayedPrice: 11.9,
                taxAmount: 1.98,
                totalPrice: 11.9,
                merchantPrice: 9.92,
            },
        ]);
    });

    it('should add the tax on top of the net price in net mode', async () => {
        const wrapper = await createWrapper('net');

        expect(wrapper.vm.examples).toEqual([
            {
                key: 'tax-rate-19',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryA',
                taxRate: 19,
                displayedPrice: 10,
                taxAmount: 1.9,
                totalPrice: 11.9,
                merchantPrice: 10,
            },
            {
                key: 'tax-rate-20',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryB',
                taxRate: 20,
                displayedPrice: 10,
                taxAmount: 2,
                totalPrice: 12,
                merchantPrice: 10,
            },
        ]);
    });

    it('should derive the gross price per country in gross net base mode', async () => {
        const wrapper = await createWrapper('grossNetBase');

        expect(wrapper.vm.examples).toEqual([
            {
                key: 'tax-rate-19',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryA',
                taxRate: 19,
                displayedPrice: 11.9,
                taxAmount: 1.9,
                totalPrice: 11.9,
                merchantPrice: 10,
            },
            {
                key: 'tax-rate-20',
                countryLabel: 'sw-settings-customer-group.priceDisplay.preview.countryB',
                taxRate: 20,
                displayedPrice: 12,
                taxAmount: 2,
                totalPrice: 12,
                merchantPrice: 10,
            },
        ]);
    });

    it.each([
        [
            'gross',
            'sw-settings-customer-group.priceDisplay.preview.inclVat',
            'total',
            'merchant',
        ],
        [
            'net',
            'sw-settings-customer-group.priceDisplay.preview.exclVat',
            'merchant',
            'total',
        ],
        [
            'grossNetBase',
            'sw-settings-customer-group.priceDisplay.preview.inclVat',
            'merchant',
            'total',
        ],
    ])('should emphasise the invariant value in %s mode', async (mode, priceNote, fixedRow, varyingRow) => {
        const wrapper = await createWrapper(mode);

        expect(wrapper.vm.displayedPriceNote).toBe(priceNote);
        expect(wrapper.vm.fixedRow).toBe(fixedRow);
        expect(wrapper.vm.varyingRow).toBe(varyingRow);

        expect(wrapper.find(`.sw-settings-customer-group-price-preview__row--${fixedRow}`).classes()).toContain('is--fixed');
        expect(wrapper.find(`.sw-settings-customer-group-price-preview__row--${varyingRow}`).classes()).toContain(
            'is--varying',
        );
    });

    it('should format the prices with the currency filter', async () => {
        const wrapper = await createWrapper('gross');

        const values = wrapper
            .findAll('.sw-settings-customer-group-price-preview__country')
            .at(1)
            ?.findAll('.sw-settings-customer-group-price-preview__row-value')
            .map((value) => value.text());

        expect(values).toEqual([
            '€11.90 sw-settings-customer-group.priceDisplay.preview.inclVat',
            '€1.98',
            '€11.90',
            '€9.92',
        ]);
    });

    it('should honour custom tax rates and prices', async () => {
        const wrapper = mount(
            await wrapTestComponent('sw-settings-customer-group-price-preview', {
                sync: true,
            }),
            {
                props: {
                    mode: 'net',
                    netPrice: 100,
                    grossPrice: 107,
                    taxRates: [
                        7,
                        25,
                    ],
                },
            },
        );

        expect(wrapper.vm.examples).toEqual([
            expect.objectContaining({ taxRate: 7, totalPrice: 107, taxAmount: 7, merchantPrice: 100 }),
            expect.objectContaining({ taxRate: 25, totalPrice: 125, taxAmount: 25, merchantPrice: 100 }),
        ]);
    });
});
