/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import 'src/app/component/form/sw-price-field';

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
        value: [
            dollarPrice,
            euroPrice,
        ],
        taxRate,
        currency,
        defaultPrice,
        enableInheritance: false,
        ...propOverride,
    };

    return mount(await wrapTestComponent('sw-price-field', { sync: true }), {
        global: {
            stubs: {
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field', { sync: true }),
                'sw-block-field': await wrapTestComponent('sw-block-field', {
                    sync: true,
                }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-field-error': true,
                'sw-inheritance-switch': true,
                'sw-field-copyable': true,
                'sw-container': true,
                'sw-maintain-currencies-modal': true,
            },
        },
        props,
    });
};

describe('components/form/sw-price-field', () => {
    beforeEach(() => {
        Shopware.Application.getContainer = () => {
            return {
                apiService: {
                    getByName() {
                        return {
                            calculatePrice() {
                                return Promise.resolve({
                                    data: {
                                        calculatedTaxes: [],
                                    },
                                });
                            },
                        };
                    },
                },
            };
        };

        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it('should contain the dollar price', async () => {
        const wrapper = await setup();

        expect(wrapper.vm.priceForCurrency.gross).toEqual(dollarPrice.gross);
        expect(wrapper.vm.priceForCurrency.net).toEqual(dollarPrice.net);
    });

    it('should not be an disabled field', async () => {
        const wrapper = await setup();

        expect(wrapper.find('.sw-price-field--disabled').exists()).toBeFalsy();
    });

    it('should be an disabled field', async () => {
        const wrapper = await setup({ value: [euroPrice] });

        expect(wrapper.find('.sw-price-field--disabled').exists()).toBeTruthy();
    });

    it('should calculate price based on default price', async () => {
        const wrapper = await setup({ value: [euroPrice] });

        const dollarPriceConverted = {
            gross: euroPrice.gross * currency.factor,
            net: euroPrice.net * currency.factor,
        };

        expect(wrapper.vm.priceForCurrency.gross).toEqual(dollarPriceConverted.gross);
        expect(wrapper.vm.priceForCurrency.net + 0.0).toEqual(dollarPriceConverted.net);
    });

    it('should remove the inheritance when matching currency price exists', async () => {
        const wrapper = await setup({ value: [euroPrice] });

        expect(wrapper.vm.isInherited).toBeTruthy();
        await wrapper.setProps({
            value: [
                dollarPrice,
                euroPrice,
            ],
        });
        expect(wrapper.vm.isInherited).toBeFalsy();
    });

    it('should set gross value null when the net value is not a number and allow empty is true', async () => {
        const wrapper = await setup({ allowEmpty: true });
        wrapper.vm.convertNetToGross(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.gross).toBeNull();
    });

    it('should set gross value null when the net value is null and allow empty is true', async () => {
        const wrapper = await setup({ allowEmpty: true });
        wrapper.vm.convertNetToGross(null);
        expect(wrapper.vm.priceForCurrency.gross).toBeNull();
    });

    it('should set gross value 0 when the net value is not a number and allow empty is false', async () => {
        const wrapper = await setup({ allowEmpty: false });
        wrapper.vm.convertNetToGross(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.gross).toBe(0);
    });

    it('should set net value null when the gross value is not a number and allow empty is true', async () => {
        const wrapper = await setup({ allowEmpty: true });
        wrapper.vm.convertGrossToNet(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.net).toBeNull();
    });

    it('should set net value null when the gross value is null and allow empty is true', async () => {
        const wrapper = await setup({ allowEmpty: true });
        wrapper.vm.convertGrossToNet(null);
        expect(wrapper.vm.priceForCurrency.net).toBeNull();
    });

    it('should set net value 0 when the gross value is not a number and allow empty is false', async () => {
        const wrapper = await setup({ allowEmpty: false });
        wrapper.vm.convertGrossToNet(parseFloat(''));
        expect(wrapper.vm.priceForCurrency.net).toBe(0);
    });

    it('should calculate values if inherited and price is not set', async () => {
        const wrapper = await setup({ allowEmpty: false });
        await wrapper.setProps({
            value: [euroPrice],
        });

        const expectedNetPrice = euroPrice.net * currency.factor;

        expect(wrapper.vm.priceForCurrency.net).toBe(parseFloat(expectedNetPrice, 10));
    });

    it('should set values to null if not inherited and price is not set', async () => {
        const wrapper = await setup({ allowEmpty: false });
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        expect(wrapper.vm.priceForCurrency.net).toBeNull();
    });

    it('should pass down gross and net helptext', async () => {
        await setup({
            grossHelpText: 'help for gross price',
            netHelpText: 'help for net price',
        });

        // New help-text has teleported to document.body
        expect(document.body.innerHTML).toContain('help for gross price');
        expect(document.body.innerHTML).toContain('help for net price');
    });

    it('should set gross value when the net value is updated', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertNetToGross = jest.spyOn(wrapper.vm, 'convertNetToGross');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetInputChange(euroPrice.net);
        jest.runAllTimers();

        expect(convertNetToGross).toHaveBeenCalled();
    });

    it('should set net value when the gross value is updated', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertGrossToNet = jest.spyOn(wrapper.vm, 'convertGrossToNet');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossInputChange(euroPrice.gross);
        jest.runAllTimers();

        expect(convertGrossToNet).toHaveBeenCalled();
    });

    it('should set net value immediately when gross model change is triggered', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertGrossToNet = jest.spyOn(wrapper.vm, 'convertGrossToNet');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossModelChange(euroPrice.gross);

        expect(convertGrossToNet).toHaveBeenCalledTimes(1);
    });

    it('should set gross value immediately when net model change is triggered', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertNetToGross = jest.spyOn(wrapper.vm, 'convertNetToGross');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetModelChange(euroPrice.net);

        expect(convertNetToGross).toHaveBeenCalledTimes(1);
    });

    it('should call debounced gross handler on input change', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const onPriceGrossChangeDebounce = jest.spyOn(wrapper.vm, 'onPriceGrossChangeDebounce');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossInputChange(euroPrice.gross);

        expect(onPriceGrossChangeDebounce).toHaveBeenCalledTimes(1);
        expect(onPriceGrossChangeDebounce).toHaveBeenCalledWith(euroPrice.gross);
    });

    it('should call debounced net handler on input change', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const onPriceNetChangeDebounce = jest.spyOn(wrapper.vm, 'onPriceNetChangeDebounce');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetInputChange(euroPrice.net);

        expect(onPriceNetChangeDebounce).toHaveBeenCalledTimes(1);
        expect(onPriceNetChangeDebounce).toHaveBeenCalledWith(euroPrice.net);
    });

    it('should cancel pending debounce when gross model change is triggered', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const onPriceGrossChangeDebounceCancel = jest.fn();
        wrapper.vm.onPriceGrossChangeDebounce.cancel = onPriceGrossChangeDebounceCancel;
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossInputChange(euroPrice.gross);
        wrapper.vm.onPriceGrossModelChange(euroPrice.gross);

        expect(onPriceGrossChangeDebounceCancel).toHaveBeenCalledTimes(1);
    });

    it('should cancel pending debounce when net model change is triggered', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const onPriceNetChangeDebounceCancel = jest.fn();
        wrapper.vm.onPriceNetChangeDebounce.cancel = onPriceNetChangeDebounceCancel;
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetInputChange(euroPrice.net);
        wrapper.vm.onPriceNetModelChange(euroPrice.net);

        expect(onPriceNetChangeDebounceCancel).toHaveBeenCalledTimes(1);
    });

    it('should not emit update:value event on price gross change', async () => {
        const wrapper = await setup({ allowEmpty: false });
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossInputChange(euroPrice.gross);
        jest.runAllTimers();

        expect(wrapper.emitted('update:value')).toBeFalsy();
    });

    it('should not emit update:value event on price net change', async () => {
        const wrapper = await setup({ allowEmpty: false });
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetInputChange(euroPrice.net);
        jest.runAllTimers();

        expect(wrapper.emitted('update:value')).toBeFalsy();
    });

    it('should have the typed gross value after input change and after debounce time', async () => {
        const wrapper = await setup({ allowEmpty: true });
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceGrossInputChange(euroPrice.gross);
        jest.runAllTimers();

        expect(wrapper.vm.priceForCurrency.gross).toBe(euroPrice.gross);
    });

    it('should have the typed net value after input change and after debounce time', async () => {
        const wrapper = await setup({ allowEmpty: true });
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        wrapper.vm.onPriceNetInputChange(euroPrice.net);
        jest.runAllTimers();

        expect(wrapper.vm.priceForCurrency.net).toBe(euroPrice.net);
    });
});
