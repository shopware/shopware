import { shallowMount } from '@vue/test-utils';
import 'src/app/component/form/sw-purchase-price-field';

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

    return shallowMount(Shopware.Component.build('sw-purchase-price-field'), {
        stubs: ['sw-price-field', 'sw-field', 'sw-icon'],
        propsData
    });
};

describe('components/form/sw-purchase-price-field', () => {
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
        expect(wrapper.vm.purchasePrice[0].gross).toEqual(dollarPrice.gross);
        expect(wrapper.vm.purchasePrice[0].net).toEqual(dollarPrice.net);
    });
});
