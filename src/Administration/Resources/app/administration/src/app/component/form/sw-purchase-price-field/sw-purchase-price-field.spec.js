/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-purchase-price-field';

// mock data
const dollarPrice = {
    currencyId: 'a435755c6c4f4fb4b81ec32b4c07e06e',
    net: 250,
    gross: 123,
    linked: false,
};
const euroPrice = {
    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    net: 152.33644859813083,
    gross: 163,
    linked: true,
};

const taxRate = {
    name: '7%',
    taxRate: 7,
    id: 'd9eac12a83984df59a618a5be1342009',
};

const currency = {
    id: 'a435755c6c4f4fb4b81ec32b4c07e06e',
    name: 'US-Dollar',
    isoCode: 'USD',
    decimalPrecision: 2,
    factor: 1.17085,
    shortName: 'USD',
    symbol: '$',
};

const defaultPrice = {
    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    gross: 163,
    net: 152.33644859813083,
    linked: true,
};

// initial component setup
const setup = async (propOverride) => {
    const props = {
        price: [dollarPrice, euroPrice],
        taxRate,
        currency,
        defaultPrice,
        enableInheritance: false,
        ...propOverride,
    };

    return mount(await wrapTestComponent('sw-purchase-price-field', { sync: true }), {
        global: {
            stubs: ['sw-price-field', 'sw-field'],
        },
        props,
    });
};

describe('components/form/sw-purchase-price-field', () => {
    it('should contain the dollar price', async () => {
        const wrapper = await setup();
        expect(wrapper.vm.purchasePrice[0].gross).toEqual(dollarPrice.gross);
        expect(wrapper.vm.purchasePrice[0].net).toEqual(dollarPrice.net);
    });

    it('should replace the purchase price for the selected currency', async () => {
        const price = [structuredClone(dollarPrice), structuredClone(euroPrice)];
        const wrapper = await setup({ price });
        const updatedPrice = { ...dollarPrice, gross: 60, net: 50 };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', updatedPrice);

        expect(price).toEqual([updatedPrice, euroPrice]);
        expect(wrapper.emitted('update:value')[0]).toEqual([price]);
        wrapper.unmount();
    });

    it('should add a purchase price for a missing currency', async () => {
        const price = [structuredClone(euroPrice)];
        const wrapper = await setup({ price });
        const newPrice = { ...dollarPrice, gross: 60, net: 50 };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', newPrice);

        expect(price).toEqual([euroPrice, newPrice]);
        expect(wrapper.emitted('update:value')[0]).toEqual([price]);
        wrapper.unmount();
    });
});
