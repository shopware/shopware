/**
 * @sw-package inventory
 */
import { mount, type VueWrapper } from '@vue/test-utils';
import { defineComponent } from 'vue';
import ProductStreamConditionService from 'src/app/service/product-stream-condition.service';

type Condition = {
    type: string | null;
    value?: string | null;
    queries?: Condition[];
};

const operatorSelect = defineComponent({
    name: 'sw-single-select',
    props: [
        'value',
        'options',
    ],
    emits: ['update:value'],
    template: '<div class="sw-single-select"></div>',
});

const isEmpty: Condition = { type: 'equals', value: null };
const isNotEmpty: Condition = { type: 'not', queries: [{ type: 'equals', value: null }] };
const isEqualTo100: Condition = { type: 'equals', value: '100' };
const isNotEqualTo100: Condition = { type: 'not', queries: [{ type: 'equals', value: '100' }] };

async function createWrapper(condition: Condition, fieldName = 'cheapestPrice.percentage') {
    return mount(await wrapTestComponent('sw-product-stream-value', { sync: true }), {
        props: {
            fieldName,
            condition,
            definition: {
                entity: 'product',
                getField: () => undefined,
                isJsonField: () => false,
                filterProperties: () => ({}),
            },
        },
        global: {
            provide: {
                repositoryFactory: { create: () => ({ search: () => Promise.resolve([]) }) },
                conditionDataProviderService: ProductStreamConditionService() as unknown,
                productCustomFields: {},
                productTypes: [],
            },
            stubs: {
                'sw-single-select': operatorSelect,
                'sw-container': { template: '<div class="sw-container"><slot></slot></div>' },
                'sw-number-field': true,
            },
        },
    });
}

describe('src/module/sw-product-stream/component/sw-product-stream-value/sw-product-stream-value.empty-operator.spec.ts', () => {
    let wrapper: VueWrapper;

    afterEach(() => {
        wrapper?.unmount();
    });

    it('offers "Is empty" and "Is not empty" for the price/list price ratio', async () => {
        wrapper = await createWrapper(isEqualTo100);

        const operators = (wrapper.getComponent(operatorSelect).props('options') as Array<{ value: string }>).map(
            ({ value }) => value,
        );

        expect(operators).toContain('isEmpty');
        expect(operators).toContain('isNotEmpty');
    });

    it('does not offer them for a field every product has a value for', async () => {
        wrapper = await createWrapper(isEqualTo100, 'cheapestPrice');

        const operators = (wrapper.getComponent(operatorSelect).props('options') as Array<{ value: string }>).map(
            ({ value }) => value,
        );

        expect(operators).not.toContain('isEmpty');
        expect(operators).not.toContain('isNotEmpty');
    });

    it.each([
        ['an equals condition without a value', isEmpty, 'isEmpty'],
        ['a negated equals condition without a value', isNotEmpty, 'isNotEmpty'],
        ['an equals condition with a value', isEqualTo100, 'equals'],
        ['a negated equals condition with a value', isNotEqualTo100, 'notEquals'],
    ])('shows %s with its operator', async (_description, condition, expectedOperator) => {
        wrapper = await createWrapper(condition);

        expect(wrapper.getComponent(operatorSelect).props('value')).toBe(expectedOperator);
    });

    it('keeps "Is equal to" for a condition without a value on other fields', async () => {
        wrapper = await createWrapper(isEmpty, 'cheapestPrice');

        expect(wrapper.getComponent(operatorSelect).props('value')).toBe('equals');
    });

    it.each([
        ['isEmpty', 'equals'],
        ['isNotEmpty', 'notEquals'],
    ])('saves "%s" as a condition of type "%s" without a value', async (operator, expectedType) => {
        wrapper = await createWrapper(isEqualTo100);

        wrapper.getComponent(operatorSelect).vm.$emit('update:value', operator);

        expect(wrapper.emitted('empty-change')).toEqual([[{ type: expectedType }]]);
        expect(wrapper.emitted('type-change')).toBeUndefined();
    });

    it.each([
        ['"Is empty"', isEmpty],
        ['"Is not empty"', isNotEmpty],
    ])('shows no value input for %s', async (_description, condition) => {
        wrapper = await createWrapper(condition);

        expect(wrapper.findComponent({ ref: 'product-stream-value-single-value-component' }).exists()).toBe(false);
        expect(wrapper.find('.sw-product-stream-value__placeholder').exists()).toBe(true);
    });

    it('shows the value input for a condition with a value', async () => {
        wrapper = await createWrapper(isEqualTo100);

        expect(wrapper.findComponent({ ref: 'product-stream-value-single-value-component' }).exists()).toBe(true);
        expect(wrapper.find('.sw-product-stream-value__placeholder').exists()).toBe(false);
    });
});
