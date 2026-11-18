/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-purchase-price-field';

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

let wrapper;

const setup = async (propOverride) => {
    const props = {
        price: [structuredClone(dollarPrice), structuredClone(euroPrice)],
        taxRate,
        currency,
        defaultPrice,
        enableInheritance: false,
        ...propOverride,
    };

    wrapper = mount(await wrapTestComponent('sw-purchase-price-field', { sync: true }), {
        global: {
            stubs: {
                'sw-price-field': {
                    props: {
                        value: Array,
                        allowEmpty: Boolean,
                    },
                    template: '<div class="sw-price-field-stub" />',
                },
                'sw-field': true,
            },
        },
        props,
    });

    return wrapper;
};

describe('components/form/sw-purchase-price-field', () => {
    afterEach(() => {
        wrapper?.unmount();
    });

    it('should return the price for the selected currency', async () => {
        const wrapper = await setup();

        expect(wrapper.findComponent('.sw-purchase-price-field').props('value')).toEqual([dollarPrice]);
    });

    it('should update the purchase price', async () => {
        const price = [structuredClone(dollarPrice), structuredClone(euroPrice)];
        const wrapper = await setup({ price });
        const updatedPrice = { ...dollarPrice, gross: 60, net: 50 };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', updatedPrice);

        expect(wrapper.emitted('update:value')[0]).toEqual([[updatedPrice, euroPrice]]);
    });

    it('should add a purchase price', async () => {
        const price = [structuredClone(euroPrice)];
        const wrapper = await setup({ price });
        const newPrice = { ...dollarPrice, gross: 60, net: 50 };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', newPrice);

        expect(wrapper.emitted('update:value')[0]).toEqual([[euroPrice, newPrice]]);
    });

    it('should remove an empty purchase price', async () => {
        const price = [structuredClone(dollarPrice), structuredClone(euroPrice)];
        const wrapper = await setup({ price });
        const emptyPrice = { ...dollarPrice, gross: null, net: null };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', emptyPrice);

        expect(wrapper.emitted('update:value')[0]).toEqual([[euroPrice]]);
    });

    it('should emit null when the last purchase price is removed', async () => {
        const price = [structuredClone(dollarPrice)];
        const wrapper = await setup({ price });
        const emptyPrice = { ...dollarPrice, gross: null, net: null };

        wrapper.findComponent('.sw-purchase-price-field').vm.$emit('change', emptyPrice);

        expect(wrapper.emitted('update:value')[0]).toEqual([null]);
    });

    it('should allow an empty purchase price', async () => {
        const wrapper = await setup({ price: [] });

        expect(wrapper.findComponent('.sw-purchase-price-field').props('allowEmpty')).toBe(true);
    });
});
