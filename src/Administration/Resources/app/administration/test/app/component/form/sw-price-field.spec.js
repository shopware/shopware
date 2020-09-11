import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-price-field';

// mock data
const dollarPrice = {
    currencyId: 'a435755c6c4f4fb4b81ec32b4c07e06e',
    net: 250,
    gross: 123,
    linked: false
};
const euroPrice = {
    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    net: 152.33644859813083,
    gross: 163,
    linked: true
};

const taxRate = {
    name: '7%',
    taxRate: 7,
    id: 'd9eac12a83984df59a618a5be1342009'
};

const currency = {
    id: 'a435755c6c4f4fb4b81ec32b4c07e06e',
    name: 'US-Dollar',
    isoCode: 'USD',
    decimalPrecision: 2,
    factor: 1.17085,
    shortName: 'USD',
    symbol: '$'
};

const defaultPrice = {
    currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca',
    gross: 163,
    net: 152.33644859813083,
    linked: true
};

// initial component setup
const setup = (propOverride) => {
    const propsData = {
        price: [dollarPrice, euroPrice],
        taxRate,
        currency,
        defaultPrice,
        enableInheritance: false,
        ...propOverride
    };

    return shallowMount(Shopware.Component.build('sw-price-field'), {
        stubs: ['sw-field', 'sw-icon'],
        mocks: { $tc: key => key },
        propsData
    });
};

describe('components/form/sw-price-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = setup();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should renders correctly', async () => {
        const wrapper = setup();
        expect(wrapper.element).toMatchSnapshot();
    });

    it('should contain the dollar price', async () => {
        const wrapper = setup();
        expect(wrapper.vm.priceForCurrency.gross).toEqual(dollarPrice.gross);
        expect(wrapper.vm.priceForCurrency.net).toEqual(dollarPrice.net);
    });

    it('should not be an disabled field', async () => {
        const wrapper = setup();
        expect(wrapper.find('.sw-price-field--disabled').exists()).toBeFalsy();
    });

    it('should be an disabled field', async () => {
        const wrapper = setup({ price: [euroPrice] });
        expect(wrapper.find('.sw-price-field--disabled').exists()).toBeTruthy();
    });

    it('should calculate price based on default price', async () => {
        const wrapper = setup({ price: [euroPrice] });
        const dollarPriceConverted = {
            gross: (euroPrice.gross * currency.factor).toFixed(2),
            net: (euroPrice.net * currency.factor).toFixed(2)
        };

        expect(`${wrapper.vm.priceForCurrency.gross}`).toEqual(dollarPriceConverted.gross);
        expect(`${wrapper.vm.priceForCurrency.net}`).toEqual(dollarPriceConverted.net);
    });

    it('should remove the inheritation when matching currency price exists', async () => {
        const wrapper = setup({ price: [euroPrice] });
        expect(wrapper.vm.isInherited).toBeTruthy();
        await wrapper.setProps({ price: [dollarPrice, euroPrice] });
        expect(wrapper.vm.isInherited).toBeFalsy();
    });
});
