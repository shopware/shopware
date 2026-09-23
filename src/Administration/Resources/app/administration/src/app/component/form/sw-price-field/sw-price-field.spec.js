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
        value: [dollarPrice, euroPrice],
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
            value: [dollarPrice, euroPrice],
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

    it('should recalculate the net value when the gross number field emits an "input-change" event', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertGrossToNet = jest.spyOn(wrapper.vm, 'convertGrossToNet');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        await wrapper.findComponent('.sw-price-field__gross').vm.$emit('input-change', euroPrice.gross);
        jest.runAllTimers();

        expect(convertGrossToNet).toHaveBeenCalled();
    });

    it('should recalculate the gross value when the net number field emits an "input-change" event', async () => {
        const wrapper = await setup({ allowEmpty: false });
        const convertNetToGross = jest.spyOn(wrapper.vm, 'convertNetToGross');
        await wrapper.setProps({
            value: [euroPrice],
            inherited: false,
        });

        await wrapper.findComponent('.sw-price-field__net').vm.$emit('input-change', euroPrice.net);
        jest.runAllTimers();

        expect(convertNetToGross).toHaveBeenCalled();
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

    it('should cancel the debounce timer when the number field emits "ends-with-decimal-separator" event', async () => {
        const wrapper = await setup();

        // Type a normal number
        await wrapper.findByPlaceholder('sw-product.priceForm.placeholderPriceGross').setValue('123');

        // Wait for the debounce timer to start
        await wrapper.vm.$nextTick();

        // Type a number with a decimal separator at the end
        await wrapper.findByPlaceholder('sw-product.priceForm.placeholderPriceGross').setValue('123.');

        // Wait until the debounce timer is finished
        jest.runAllTimers();
        await flushPromises();

        // Check if the value is set
        expect(wrapper.vm.priceForCurrency.gross).toBe(123);

        // Check if the input field value still contains the decimal separator
        expect(wrapper.findByPlaceholder('sw-product.priceForm.placeholderPriceGross').element.value).toBe('123.');
    });

    describe('conversion of the value 0', () => {
        let calculatePrice;

        beforeEach(() => {
            calculatePrice = jest.fn(({ price, output }) => {
                const tax = output === 'gross' ? price - price / 1.07 : price * 0.07;

                return Promise.resolve({ data: { calculatedTaxes: [{ tax }] } });
            });

            Shopware.Application.getContainer = () => ({
                apiService: { getByName: () => ({ calculatePrice }) },
            });
        });

        const nonZeroPrice = { gross: 10.7, net: 10 };

        const setupWithPrice = (price) => setup({ value: [{ currencyId: currency.id, ...price }] });

        const settle = async () => {
            jest.runAllTimers();
            await flushPromises();
        };

        it.each([
            { changed: 'gross', converted: 'net' },
            { changed: 'net', converted: 'gross' },
        ])('should set the linked $converted value to 0 when 0 is typed into $changed', async ({ changed, converted }) => {
            const wrapper = await setupWithPrice({ ...nonZeroPrice, linked: true });

            await wrapper.find(`.sw-price-field__${changed} input`).setValue('0');
            await settle();

            expect(wrapper.vm.priceForCurrency[changed]).toBe(0);
            expect(wrapper.vm.priceForCurrency[converted]).toBe(0);
        });

        it.each([
            { changed: 'gross', converted: 'net', expectedAtStep: 0.01 / 1.07 },
            { changed: 'net', converted: 'gross', expectedAtStep: 0.01 * 1.07 },
        ])(
            'should update the linked $converted value when $changed is stepped from 0.01 back to 0 with the arrow keys',
            async ({ changed, converted, expectedAtStep }) => {
                const wrapper = await setupWithPrice({ gross: 0, net: 0, linked: true });
                const input = wrapper.find(`.sw-price-field__${changed} input`);

                await input.trigger('keydown', { key: 'ArrowUp' });
                await settle();

                expect(wrapper.vm.priceForCurrency[changed]).toBe(0.01);
                expect(wrapper.vm.priceForCurrency[converted]).toBeCloseTo(expectedAtStep, 10);

                await input.trigger('keydown', { key: 'ArrowDown' });
                await settle();

                expect(wrapper.vm.priceForCurrency[changed]).toBe(0);
                expect(wrapper.vm.priceForCurrency[converted]).toBe(0);
            },
        );

        it.each([
            { changed: 'gross', converted: 'net' },
            { changed: 'net', converted: 'gross' },
        ])('should keep the unlinked $converted value when $changed is set to 0', async ({ changed, converted }) => {
            const wrapper = await setupWithPrice({ ...nonZeroPrice, linked: false });

            await wrapper.find(`.sw-price-field__${changed} input`).setValue('0');
            await settle();

            expect(wrapper.vm.priceForCurrency[changed]).toBe(0);
            expect(wrapper.vm.priceForCurrency[converted]).toBe(nonZeroPrice[converted]);
            expect(calculatePrice).not.toHaveBeenCalled();
        });

        it('should resolve a tax value of 0 for a price of 0 without calling the API', async () => {
            const wrapper = await setupWithPrice({ gross: 0, net: 0, linked: true });
            const onResolve = jest.fn();

            wrapper.vm.requestTaxValue(0, 'gross').then(onResolve);
            await flushPromises();

            expect(onResolve).toHaveBeenCalledWith(0);
            expect(calculatePrice).not.toHaveBeenCalled();
            expect(wrapper.emitted('price-calculate').at(-1)).toEqual([false]);
        });
    });
});
