/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import { RULE_BETWEEN_OPERATOR_MIXIN_NAME } from 'src/app/mixin/rule-between-operator.mixin';

const defaultData = {
    condition: {
        value: {
            operator: 'between',
        },
    },
};

async function createWrapper(data = defaultData) {
    return mount(
        {
            template: `
            <div class="sw-mock">
                <div v-if="isBetween" class="sw-mock__between-check"></div>
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName(RULE_BETWEEN_OPERATOR_MIXIN_NAME),
            ],
            data() {
                return data;
            },
        },
        {
            attachTo: document.body,
            global: {
                mocks: {
                    ensureValueExist: () => {},
                },
            },
        },
    );
}

describe('src/app/mixin/rule-between-operator.mixin', () => {
    it.each([
        { name: 'is between operator', operator: 'between', expected: true },
        { name: 'is not between operator', operator: '=', expected: false },
        { name: 'is null', operator: null, expected: false },
    ])('should validate if condition operator is between: $name', async ({ operator, expected }) => {
        const wrapper = await createWrapper({
            ...defaultData,
            condition: {
                ...defaultData.condition,
                value: {
                    ...defaultData.condition.value,
                    operator,
                },
            },
        });

        expect(wrapper.find('.sw-mock__between-check').exists()).toBe(expected);
    });

    it.each([
        { name: 'empty condition', condition: {}, expected: { from: null, to: null } },
        { name: 'empty condition value', condition: { value: [] }, expected: { from: null, to: null } },
        {
            name: 'empty condition rendered field value',
            condition: { value: { renderedFieldValue: null } },
            expected: { from: null, to: null },
        },
        {
            name: 'extract field value',
            condition: { value: { renderedFieldValue: { from: 'from', to: 'to' } } },
            expected: { from: 'from', to: 'to' },
        },
    ])('should get between value: $name', async ({ condition, expected }) => {
        const wrapper = await createWrapper({
            ...defaultData,
            condition,
        });

        expect(wrapper.vm.betweenValue).toEqual(expected);
    });

    it.each([
        { name: 'from only', value: { from: 'from', to: null } },
        { name: 'to only', value: { from: null, to: 'to' } },
        { name: 'from and to', value: { from: 'from', to: 'to' } },
    ])('should set condition value: $name', async ({ value }) => {
        const wrapper = await createWrapper({ condition: { value: {} } });

        wrapper.vm.betweenValue = value;

        expect(wrapper.vm.condition.value.renderedFieldValue).toEqual(value);
    });
});
