/**
 * @sw-package inventory
 */
import { mount } from '@vue/test-utils';

type PriceKey = 'gross' | 'net';

interface Price {
    currencyId: string;
    gross: number | null;
    net: number | null;
    linked: boolean;
}

const nonZeroPrice = { gross: 10.7, net: 10 };

const calculatePrice = jest.fn(({ price, output }: { price: number; output: PriceKey }) => {
    const tax = output === 'gross' ? price - price / 1.07 : price * 0.07;

    return Promise.resolve({ data: { calculatedTaxes: [{ tax }] } });
});

async function createWrapper(priceOverride: Partial<Price>) {
    const price: Price = {
        currencyId: 'currency-id',
        ...nonZeroPrice,
        linked: true,
        ...priceOverride,
    };

    // The component writes the converted values directly into the passed price object
    const wrapper = mount(await wrapTestComponent('sw-product-variants-price-field', { sync: true }), {
        props: {
            price,
            taxRate: 'tax-id',
            currency: { id: 'currency-id', decimalPrecision: 2 },
        },
    });

    return { wrapper, price };
}

function findInput(wrapper: Awaited<ReturnType<typeof createWrapper>>['wrapper'], changed: PriceKey) {
    return wrapper.findAll('.sw-product-variants-price-field__input input').at(changed === 'gross' ? 0 : 1)!;
}

async function settle() {
    jest.runAllTimers();
    await flushPromises();
}

describe('src/module/sw-product/component/sw-product-variants/sw-product-variants-configurator/sw-product-variants-price-field', () => {
    beforeEach(() => {
        calculatePrice.mockClear();

        Shopware.Application.getContainer = (() => ({
            apiService: { getByName: () => ({ calculatePrice }) },
        })) as unknown as typeof Shopware.Application.getContainer;

        jest.useFakeTimers();
    });

    afterEach(() => {
        jest.useRealTimers();
    });

    it.each([
        { changed: 'gross', converted: 'net', expected: 5 },
        { changed: 'net', converted: 'gross', expected: 10.7 },
    ] as const)('should convert the linked $changed value to $converted', async ({ changed, converted, expected }) => {
        const { wrapper, price } = await createWrapper({ gross: 0, net: 0 });

        await findInput(wrapper, changed).setValue(changed === 'gross' ? '5.35' : '10');
        await findInput(wrapper, changed).trigger('change');
        await settle();

        expect(price[converted]).toBeCloseTo(expected, 10);
    });

    it.each([
        { changed: 'gross', converted: 'net' },
        { changed: 'net', converted: 'gross' },
    ] as const)('should set the linked $converted value to 0 when $changed is set to 0', async ({ changed, converted }) => {
        const { wrapper, price } = await createWrapper({});

        await findInput(wrapper, changed).setValue('0');
        await findInput(wrapper, changed).trigger('change');
        await settle();

        expect(price[changed]).toBe(0);
        expect(price[converted]).toBe(0);
        expect(calculatePrice).not.toHaveBeenCalled();
    });

    it.each([
        { changed: 'gross', converted: 'net', expectedAtStep: 0.01 / 1.07 },
        { changed: 'net', converted: 'gross', expectedAtStep: 0.01 * 1.07 },
    ] as const)(
        'should update the linked $converted value when $changed is stepped from 0.01 back to 0 with the arrow keys',
        async ({ changed, converted, expectedAtStep }) => {
            const { wrapper, price } = await createWrapper({ gross: 0, net: 0 });

            await findInput(wrapper, changed).trigger('keydown', { key: 'ArrowUp' });
            await settle();

            expect(price[changed]).toBe(0.01);
            expect(price[converted]).toBeCloseTo(expectedAtStep, 10);

            await findInput(wrapper, changed).trigger('keydown', { key: 'ArrowDown' });
            await settle();

            expect(price[changed]).toBe(0);
            expect(price[converted]).toBe(0);
        },
    );

    it.each([
        { changed: 'gross', converted: 'net' },
        { changed: 'net', converted: 'gross' },
    ] as const)('should keep the unlinked $converted value when $changed is set to 0', async ({ changed, converted }) => {
        const { wrapper, price } = await createWrapper({ linked: false });

        await findInput(wrapper, changed).setValue('0');
        await findInput(wrapper, changed).trigger('change');
        await settle();

        expect(price[changed]).toBe(0);
        expect(price[converted]).toBe(nonZeroPrice[converted]);
        expect(calculatePrice).not.toHaveBeenCalled();
    });

    it('should recalculate the net value when the price gets linked', async () => {
        const { wrapper, price } = await createWrapper({ gross: 5.35, net: 1, linked: false });

        await wrapper.find('.sw-product-variants-price-field__lock').trigger('click');
        await flushPromises();

        expect(price.linked).toBe(true);
        expect(price.net).toBeCloseTo(5, 10);
    });

    it('should set the net value to 0 when a price with a gross value of 0 gets linked', async () => {
        const { wrapper, price } = await createWrapper({ gross: 0, net: 1, linked: false });

        await wrapper.find('.sw-product-variants-price-field__lock').trigger('click');
        await flushPromises();

        expect(price.net).toBe(0);
    });
});
