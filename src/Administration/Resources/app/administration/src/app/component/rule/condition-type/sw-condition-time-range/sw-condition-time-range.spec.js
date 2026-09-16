/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-time-range';
import 'src/app/component/rule/sw-condition-base';

Shopware.Service().register('timezoneService', () => ({
    getTimezoneOptions: () => [
        { label: 'UTC', value: 'UTC' },
        { label: 'Europe/Berlin', value: 'Europe/Berlin' },
    ],
}));

const datepickerStub = {
    name: 'mt-datepicker',
    props: [
        'modelValue',
        'name',
    ],
    emits: ['update:modelValue'],
    template: `
        <div :class="['mt-datepicker-stub', name]" :data-model-value="modelValue ?? ''">
            <input :name="name" :value="modelValue ?? ''" @input="$emit('update:modelValue', $event.target.value)" />
        </div>
    `,
};

async function createWrapper(condition = {}) {
    return mount(await wrapTestComponent('sw-condition-time-range', { sync: true }), {
        propsData: {
            condition: {
                getEntityName: () => 'rule_condition',
                ...condition,
            },
        },
        global: {
            stubs: {
                'mt-datepicker': datepickerStub,
                'sw-single-select': true,
                'sw-condition-base': true,
                'sw-condition-type-select': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-condition-field-errors': true,
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
                availableGroups: [],
                childAssociationField: {},
                validationService: {},
                insertNodeIntoTree: () => ({}),
                removeNodeFromTree: () => ({}),
                createCondition: () => ({}),
                conditionScopes: [],
                unwrapAllLineItemsCondition: () => ({}),
            },
        },
    });
}

describe('component/rule/sw-condition-time-range', () => {
    it('renders both time pickers with empty model values when the condition is empty', async () => {
        const condition = { value: {} };
        const wrapper = await createWrapper(condition);

        const fromTime = wrapper.find('input[name="sw-field--fromTime"]');
        const toTime = wrapper.find('input[name="sw-field--toTime"]');

        expect(fromTime.attributes('value')).toBe('');
        expect(toTime.attributes('value')).toBe('');

        expect(condition.value.fromTime).toBeUndefined();
        expect(condition.value.toTime).toBeUndefined();
    });

    it('renders the existing fromTime and toTime values when the condition is already populated', async () => {
        const condition = {
            value: { fromTime: '08:15', toTime: '20:45', timezone: 'UTC' },
        };

        const wrapper = await createWrapper(condition);

        expect(wrapper.find('input[name="sw-field--fromTime"]').attributes('value')).toBe('08:15');
        expect(wrapper.find('input[name="sw-field--toTime"]').attributes('value')).toBe('20:45');
    });
});
