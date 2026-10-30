import { mount, type VueWrapper } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

const wrappers: VueWrapper[] = [];

/**
 * Two quantity prices: 1 to 1 and 2 to unlimited.
 *
 * @private
 */
export function createQuantityPrices() {
    return [1, 2].map((quantityStart) => ({
        _isNew: true,
        id: `priceId${quantityStart}`,
        shippingMethodId: 'shippingMethodId',
        quantityStart,
        quantityEnd: quantityStart === 1 ? 1 : null,
        ruleId: 'ruleId',
        rule: {},
        calculation: 1,
        currencyPrice: [{ currencyId: 'euro', gross: 0, linked: false, net: 0 }],
    }));
}

/**
 * Puts a shipping method with its currencies into the store, as the shipping detail page does,
 * and returns the mock its prices are removed with.
 *
 * @private
 */
export function setUpShippingMethod(): jest.Mock {
    const remove = jest.fn();

    Shopware.Store.get('swShippingDetail').shippingMethod = {
        id: 'shippingMethodId',
        taxType: 'auto',
        taxId: null,
        prices: Object.assign([], { remove }),
    } as never;
    Shopware.Store.get('swShippingDetail').currencies = [
        { id: 'euro', isoCode: 'EUR', factor: 1, translated: { name: 'Euro' }, isSystemDefault: true },
        { id: 'dollar', isoCode: 'USD', factor: 2, translated: { name: 'Dollar' }, salesChannels: [{}] },
    ] as never;

    return remove;
}

/**
 * Mounts the price matrix for a price group, with the data grid rendered so tests can click its rows.
 *
 * @private
 */
export async function mountPriceMatrix(priceGroup: Record<string, unknown> = {}): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-settings-shipping-price-matrix', { sync: true }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-container': true,
                'sw-select-rule-create': { props: ['ruleFilter'], template: '<div />' },
                'sw-context-button': true,
                'sw-data-grid': await wrapTestComponent('sw-data-grid', { sync: true }),
                'sw-context-menu-item': { props: ['disabled'], template: '<button :disabled="disabled"><slot /></button>' },
                'sw-price-rule-modal': true,
                'mt-number-field': true,
                'sw-price-field': { template: '<div class="sw-price-field" />' },
                'sw-inheritance-switch': true,
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper', { sync: true }),
                'sw-single-select': true,
            },
        },
        props: {
            priceGroup: {
                isNew: false,
                ruleId: 'ruleId',
                rule: {},
                calculation: 1,
                prices: createQuantityPrices(),
                ...priceGroup,
            },
        },
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper;
}

/**
 * Turns the prices into rule prices, as a matrix that calculates by rule has them.
 *
 * @private
 */
export function toRulePrices(prices: Array<Record<string, unknown>>) {
    return prices.map((price, index) => ({
        ...price,
        calculation: null,
        calculationRuleId: `calculationRule${index}`,
        quantityStart: null,
        quantityEnd: null,
    }));
}

/**
 * Clicks the delete button of a price row; the first row is 0.
 *
 * @private
 */
export async function deletePrice(wrapper: VueWrapper, row: number): Promise<void> {
    await wrapper.findAll('tbody .sw-data-grid__row')[row].find('button').trigger('click');
}

/**
 * Shows every price row, because the matrix shows only the first until asked.
 *
 * @private
 */
export async function showAllPrices(wrapper: VueWrapper): Promise<void> {
    await wrapper.find('.sw-settings-shipping-price-matrix__price-load-all-button').trigger('click');
}

/**
 * Unmounts every price matrix the tests mounted.
 *
 * @private
 */
export function unmountPriceMatrices(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}
