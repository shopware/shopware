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
        propsData
    });
};

describe('components/form/sw-price-field', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = setup();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
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
            gross: euroPrice.gross * currency.factor,
            net: euroPrice.net * currency.factor
        };

        expect(wrapper.vm.priceForCurrency.gross).toEqual(dollarPriceConverted.gross);
        expect(wrapper.vm.priceForCurrency.net + 0.0).toEqual(dollarPriceConverted.net);
    });

    it('should remove the inheritation when matching currency price exists', async () => {
        const wrapper = setup({ price: [euroPrice] });
        expect(wrapper.vm.isInherited).toBeTruthy();
        await wrapper.setProps({ price: [dollarPrice, euroPrice] });
        expect(wrapper.vm.isInherited).toBeFalsy();
    });

    it('should set gross value null when the net value is not a number and allow empty is true', () => {
        const wrapper = setup({ allowEmpty: true });
        wrapper.vm.convertNetToGross(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.gross).toBe(null);
    });

    it('should set gross value 0 when the net value is not a number and allow empty is false', () => {
        const wrapper = setup({ allowEmpty: false });
        wrapper.vm.convertNetToGross(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.gross).toBe(0);
    });

    it('should set net value null when the gross value is not a number and allow empty is true', () => {
        const wrapper = setup({ allowEmpty: true });
        wrapper.vm.convertGrossToNet(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.net).toBe(null);
    });

    it('should set net value 0 when the gross value is not a number and allow empty is false', () => {
        const wrapper = setup({ allowEmpty: false });
        wrapper.vm.convertGrossToNet(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.net).toBe(0);
    });

    it('should calculate values if inherited and price is not set', () => {
        const wrapper = setup({ allowEmpty: false });
        wrapper.setProps({
            price: [euroPrice]
        });

        const expectedNetPrice = (euroPrice.net * currency.factor);

        expect(wrapper.vm.priceForCurrency.net).toBe(parseFloat(expectedNetPrice, 10));
    });

    it('should set values to null if not inherited and price is not set', () => {
        const wrapper = setup({ allowEmpty: false });
        wrapper.setProps({
            price: [euroPrice],
            inherited: false
        });

        expect(wrapper.vm.priceForCurrency.net).toBeNull();
    });

    it('should pass down gross and net helptext', () => {
        const wrapper = setup({
            grossHelpText: 'help for gross price',
            netHelpText: 'help for net price'
        });

        expect(wrapper.find('.sw-price-field__gross').attributes()['help-text']).toBe('help for gross price');
        expect(wrapper.find('.sw-price-field__net').attributes()['help-text']).toBe('help for net price');
    });
});
