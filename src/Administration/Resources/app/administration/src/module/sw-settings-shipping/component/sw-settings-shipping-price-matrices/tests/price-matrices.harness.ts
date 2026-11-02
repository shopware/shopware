import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import { useShippingDetailStore } from '../dependencies';

/**
 * @sw-package checkout
 */

/**
 * The button under the matrices that adds another price matrix.
 *
 * @private
 */
export const BUTTON_ADD_PRICE_MATRIX = '.sw-settings-shipping-price-matrices__actions-add-matrix';

/**
 * One price matrix, one per price group of the shipping method. The harness renders it as a stub.
 *
 * @private
 */
export const PRICE_MATRIX = 'sw-settings-shipping-price-matrix';

const wrappers: VueWrapper[] = [];

const PriceMatrixStub = defineComponent({
    name: PRICE_MATRIX,
    props: ['priceGroup', 'disabled'],
    emits: ['duplicate-price-matrix', 'delete-price-matrix'],
    template: '<div class="sw-settings-shipping-price-matrix" />',
});

/**
 * A price of the shipping method, in the matrix of the rule `ruleId`; `null` is the matrix without a rule.
 *
 * @private
 */
export function createPrice(id: string, ruleId: string | null, overrides: Record<string, unknown> = {}) {
    return {
        id,
        shippingMethodId: 'shippingMethodId',
        ruleId,
        calculation: 1,
        calculationRule: null,
        calculationRuleId: null,
        quantityStart: 1,
        quantityEnd: null,
        currencyPrice: [{ currencyId: 'euro', gross: 10, net: 8, linked: false }],
        ...overrides,
    };
}

/**
 * Puts a shipping method with these prices into the store, as the shipping detail page does.
 * Its prices can `add()` like an entity collection.
 *
 * @private
 */
export function setUpShippingMethod(prices: Array<Record<string, unknown>>): void {
    useShippingDetailStore().shippingMethod = {
        id: 'shippingMethodId',
        prices: Object.assign(prices, {
            add(this: Array<Record<string, unknown>>, price: Record<string, unknown>) {
                this.push(price);
            },
        }),
    } as never;
}

/**
 * Returns the prices of the shipping method in the store, which the matrices add to and delete from.
 *
 * @private
 */
export function getShippingMethodPrices(): Array<Record<string, unknown>> {
    return useShippingDetailStore().shippingMethod.prices as never;
}

/**
 * Mounts the price matrices. Each price matrix is a stub that a test lets emit the events of the real one.
 *
 * @private
 */
export async function mountPriceMatrices(props: { disabled?: boolean } = {}): Promise<VueWrapper> {
    const wrapper = mount(await wrapTestComponent('sw-settings-shipping-price-matrices', { sync: true }), {
        global: {
            stubs: {
                'sw-settings-shipping-price-matrix': PriceMatrixStub,
            },
            provide: {
                ruleConditionDataProviderService: {},
                repositoryFactory: {
                    create: () => ({
                        create: () => ({}),
                    }),
                },
            },
        },
        props,
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper;
}

/**
 * Lets the price matrix at this position ask to be duplicated, as its context menu does; the first is 0.
 *
 * @private
 */
export async function duplicatePriceMatrix(wrapper: VueWrapper, position: number): Promise<void> {
    const matrix = wrapper.findAllComponents(PriceMatrixStub)[position];
    matrix.vm.$emit('duplicate-price-matrix', matrix.props('priceGroup'));
    await flushPromises();
}

/**
 * Lets the price matrix at this position ask to be deleted, as its delete modal does; the first is 0.
 *
 * @private
 */
export async function deletePriceMatrix(wrapper: VueWrapper, position: number): Promise<void> {
    const matrix = wrapper.findAllComponents(PriceMatrixStub)[position];
    matrix.vm.$emit('delete-price-matrix', matrix.props('priceGroup'));
    await flushPromises();
}

/**
 * Clicks the button that adds a price matrix.
 *
 * @private
 */
export async function addPriceMatrix(wrapper: VueWrapper): Promise<void> {
    await wrapper.find(BUTTON_ADD_PRICE_MATRIX).trigger('click');
    await flushPromises();
}

/**
 * Returns the price groups the price matrices show, in order.
 *
 * @private
 */
export function getShownPriceGroups(wrapper: VueWrapper): Array<Record<string, unknown>> {
    return wrapper.findAllComponents({ name: PRICE_MATRIX }).map((matrix) => matrix.props('priceGroup') as never);
}

/**
 * Unmounts every price matrices component the tests mounted.
 *
 * @private
 */
export function unmountPriceMatrices(): void {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
}
