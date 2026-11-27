/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import { deprecations, useProductionDeprecationGuard } from 'test/_helper_/deprecations';
import 'src/app/component/form/sw-list-price-field';

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
const purchasePrices = [
    {
        currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
        gross: 100,
        net: 84.03,
        linked: true,
    },
];

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

    wrapper = mount(await wrapTestComponent('sw-list-price-field', { sync: true }), {
        global: {
            stubs: {
                'sw-price-field': {
                    props: ['value', 'inherited'],
                    template: '<div class="sw-price-field-stub" />',
                },
            },
        },
        props,
    });

    return wrapper;
};

describe('components/form/sw-list-price-field', () => {
    afterEach(() => {
        wrapper?.unmount();
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should reject legacy purchasePrices', async () => {
        useProductionDeprecationGuard();

        await expect(setup({ purchasePrices: [] })).rejects.toThrow(
            'The prop "purchasePrices" of the component "sw-list-price-field__wrapped" is deprecated',
        );
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should reject legacy hidePurchasePrices', async () => {
        useProductionDeprecationGuard();

        await expect(setup({ hidePurchasePrices: true })).rejects.toThrow(
            'The prop "hidePurchasePrices" of the component "sw-list-price-field__wrapped" is deprecated',
        );
    });

    it('should set listPrice null when the gross value is NaN', async () => {
        const price = structuredClone(dollarPrice);
        const wrapper = await setup({ price: [price] });
        const listPrice = {
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            gross: parseFloat(''),
            linked: true,
            net: 1,
        };
        await wrapper.findComponent('.sw-list-price-field__list-price-field').vm.$emit('change', listPrice);
        expect(price.listPrice).toBeNull();
    });

    it('should set listPrice null when the net value is NaN', async () => {
        const price = structuredClone(dollarPrice);
        const wrapper = await setup({ price: [price] });
        const listPrice = {
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            gross: 1,
            linked: true,
            net: parseFloat(''),
        };
        await wrapper.findComponent('.sw-list-price-field__list-price-field').vm.$emit('change', listPrice);
        expect(price.listPrice).toBeNull();
    });

    it('should not render purchase prices when the deprecated prop is not supplied', async () => {
        const wrapper = await setup();
        await flushPromises();

        expect(wrapper.props('purchasePrices')).toBeUndefined();
        expect(wrapper.find('.sw-list-price-field__purchase-price').exists()).toBeFalsy();
    });

    it('should render purchase prices when the deprecated prop is supplied later', async () => {
        const wrapper = await setup();
        await flushPromises();

        await wrapper.setProps({ purchasePrices });

        expect(wrapper.find('.sw-purchase-price-field').exists()).toBe(true);
    });

    it('should render purchase prices when the deprecated prop is supplied', async () => {
        const wrapper = await setup({ purchasePrices });
        await flushPromises();

        expect(deprecations).toHaveTriggered('The prop "purchasePrices" of the component "sw-list-price-field"');
        const purchasePriceField = wrapper.findComponent('.sw-purchase-price-field');
        expect(purchasePriceField.exists()).toBe(true);
        expect(purchasePriceField.props('value')).toEqual(purchasePrices);
    });

    it('should hide supplied purchase prices when the deprecated hide prop is set', async () => {
        const wrapper = await setup({ purchasePrices, hidePurchasePrices: true });
        await flushPromises();

        expect(deprecations).toHaveTriggered('The prop "purchasePrices" of the component "sw-list-price-field"');
        expect(deprecations).toHaveTriggered('The prop "hidePurchasePrices" of the component "sw-list-price-field"');
        expect(wrapper.find('.sw-list-price-field__purchase-price').exists()).toBe(false);
    });

    it('should set the correct inherited state when inherited', async () => {
        const wrapper = await setup();
        await wrapper.setProps({
            price: [euroPrice],
        });

        expect(wrapper.findComponent('.sw-list-price-field__list-price-field').props('inherited')).toBe(true);
    });

    it('should set the correct inherited state when not inherited', async () => {
        const wrapper = await setup();

        await wrapper.setProps({
            price: [dollarPrice],
        });

        expect(wrapper.findComponent('.sw-list-price-field__list-price-field').props('inherited')).toBe(false);
    });

    it('should not display gross help text when not in vertical mode', async () => {
        const wrapper = await setup();

        expect(
            wrapper.find('.sw-list-price-field__list-price .sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();
    });

    it('should display gross help text when in vertical mode', async () => {
        const wrapper = await setup({
            vertical: true,
        });

        expect(wrapper.find('.sw-list-price-field__list-price .sw-price-field-stub').attributes()['gross-help-text']).toBe(
            'global.sw-list-price-field.helpTextListPriceGross',
        );
    });

    it('should not display gross help text when in compact mode', async () => {
        const wrapper = await setup({
            vertical: true,
            compact: true,
        });

        expect(
            wrapper.find('.sw-list-price-field__list-price .sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();

        expect(
            wrapper.find('.sw-list-price-field__regulation-price .sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();
    });

    it('should return the default regulation price when it does not exist', async () => {
        const wrapper = await setup();

        expect(wrapper.findComponent('.sw-list-price-field__regulation-price-field').props('value')).toEqual([
            {
                gross: null,
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
                linked: true,
                net: null,
            },
        ]);
    });

    it('should set the regulation price correctly', async () => {
        const price = structuredClone(dollarPrice);
        const wrapper = await setup({ price: [price] });
        const newRegulationPrice = {
            gross: 200,
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            linked: true,
            net: 186.92,
        };

        await wrapper.findComponent('.sw-list-price-field__regulation-price-field').vm.$emit('change', newRegulationPrice);

        expect(price.regulationPrice).toEqual(newRegulationPrice);
    });
});
