/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-list-price-field';

// mock data
const purchasePrices = {
    currencyId: 'a435755c6c4f4fb4b81ec32b4c07e06e',
    net: 20,
    gross: 25,
    linked: false,
};
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
        price: [
            dollarPrice,
            euroPrice,
        ],
        purchasePrices: [purchasePrices],
        taxRate,
        currency,
        defaultPrice,
        enableInheritance: false,
        ...propOverride,
    };

    return mount(await wrapTestComponent('sw-list-price-field', { sync: true }), {
        global: {
            stubs: ['sw-price-field'],
        },
        props,
    });
};

describe('components/form/sw-list-price-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await setup();
        await flushPromises();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should set listPrice null when the gross value is NaN', async () => {
        const wrapper = await setup();
        const listPrice = {
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            gross: parseFloat(''),
            linked: true,
            net: 1,
        };
        await wrapper.vm.listPriceChanged(listPrice);
        expect(wrapper.vm.priceForCurrency.listPrice).toBeNull();
    });

    it('should set listPrice null when the net value is NaN', async () => {
        const wrapper = await setup();
        const listPrice = {
            currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
            gross: 1,
            linked: true,
            net: parseFloat(''),
        };
        await wrapper.vm.listPriceChanged(listPrice);
        expect(wrapper.vm.priceForCurrency.listPrice).toBeNull();
    });

    it('should set purchasePrice to default value when the input purchasePrices is empty', async () => {
        const wrapper = await setup({ hidePurchasePrices: true });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-list-price-field__purchase-price').exists()).toBeFalsy();
    });

    it('should set the correct inherited state when inherited', async () => {
        const wrapper = await setup();
        await wrapper.setProps({
            price: [euroPrice],
        });

        expect(wrapper.vm.isInherited).toBeTruthy();
    });

    it('should set the correct inherited state when not inherited', async () => {
        const wrapper = await setup();

        await wrapper.setProps({
            price: [dollarPrice],
        });

        expect(wrapper.vm.isInherited).toBeFalsy();
    });

    it('should not display gross help text when not in vertical mode', async () => {
        const wrapper = await setup();

        expect(
            wrapper.find('.sw-list-price-field__list-price sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();
    });

    it('should display gross help text when in vertical mode', async () => {
        const wrapper = await setup({
            vertical: true,
        });

        expect(wrapper.find('.sw-list-price-field__list-price sw-price-field-stub').attributes()['gross-help-text']).toBe(
            'global.sw-list-price-field.helpTextListPriceGross',
        );
    });

    it('should not display gross help text when in compact mode', async () => {
        const wrapper = await setup({
            vertical: true,
            compact: true,
        });

        expect(
            wrapper.find('.sw-list-price-field__list-price sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();

        expect(
            wrapper.find('.sw-list-price-field__regulation-price sw-price-field-stub').attributes()['gross-help-text'],
        ).toBeUndefined();
    });
});
