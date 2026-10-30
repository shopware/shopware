/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-weekday';
import 'src/app/component/rule/sw-condition-base';

Shopware.Service().register('timezoneService', () => ({
    getTimezoneOptions: () => [
        { label: 'UTC', value: 'UTC' },
        { label: 'Europe/Berlin', value: 'Europe/Berlin' },
    ],
}));

async function createWrapper(condition = {}) {
    return mount(await wrapTestComponent('sw-condition-weekday', { sync: true }), {
        propsData: {
            condition: {
                getEntityName: () => 'rule_condition',
                ...condition,
            },
        },
        global: {
            stubs: {
                'sw-single-select': true,
                'sw-condition-operator-select': true,
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

describe('component/rule/sw-condition-weekday', () => {
    it('renders operator, weekday and timezone fields', async () => {
        const wrapper = await createWrapper({ value: {} });

        expect(wrapper.find('sw-condition-operator-select-stub').exists()).toBe(true);
        expect(wrapper.find('sw-single-select-stub[name="sw-field--dayOfWeek"]').exists()).toBe(true);
        expect(wrapper.find('sw-single-select-stub[name="sw-field--timezone"]').exists()).toBe(true);
    });

    it('offers all seven weekdays and the timezone options', async () => {
        const wrapper = await createWrapper({ value: {} });

        expect(wrapper.vm.dayOfWeekOptions.map((option) => option.value)).toEqual([
            1,
            2,
            3,
            4,
            5,
            6,
            7,
        ]);
        expect(wrapper.vm.timezoneOptions).toEqual([
            { label: 'UTC', value: 'UTC' },
            { label: 'Europe/Berlin', value: 'Europe/Berlin' },
        ]);
    });

    it('reads existing values from the condition', async () => {
        const wrapper = await createWrapper({ value: { dayOfWeek: 3, timezone: 'Europe/Berlin' } });

        expect(wrapper.vm.dayOfWeek).toBe(3);
        expect(wrapper.vm.timezone).toBe('Europe/Berlin');
    });

    it('writes weekday and timezone back to the condition', async () => {
        const wrapper = await createWrapper({ value: {} });

        wrapper.vm.dayOfWeek = 5;
        wrapper.vm.timezone = 'UTC';

        expect(wrapper.vm.condition.value).toEqual({ dayOfWeek: 5, timezone: 'UTC' });
    });
});
