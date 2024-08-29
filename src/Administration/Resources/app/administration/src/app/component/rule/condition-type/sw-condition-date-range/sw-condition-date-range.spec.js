/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-date-range';
import 'src/app/component/rule/sw-condition-base';

async function createWrapper(condition = {}) {
    condition.getEntityName = () => 'rule_condition';

    return mount(await wrapTestComponent('sw-condition-date-range', { sync: true }), {
        propsData: {
            condition,
        },
        global: {
            stubs: {
                'sw-single-select': true,
                'sw-condition-base': true,
                'sw-condition-type-select': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-datepicker': true,
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


describe('component/rule/sw-condition-date-range', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should fromDate format have a suffix timezone', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({
            condition: {
                value: {
                    useTime: true,
                    fromDate: '2020-01-01T00:00:00.000Z',
                },
            },
        });

        const getFromDate = wrapper.vm.fromDate;
        expect(getFromDate).toBe('2020-01-01T00:00:00.000Z');

        wrapper.vm.fromDate = '2020-01-01T00:00:00+00:00';
        expect(wrapper.vm.fromDate).toBe('2020-01-01T00:00:00+00:00');
    });

    it('should toDate format have a suffix timezone', async () => {
        wrapper = await createWrapper();
        await wrapper.setProps({
            condition: {
                value: {
                    useTime: false,
                    toDate: '2020-01-01T00:00:00',
                },
            },
        });

        const getToDate = wrapper.vm.toDate;
        expect(getToDate).toBe('2020-01-01T00:00:00');

        wrapper.vm.toDate = '2020-01-01T00:00:00';
        expect(wrapper.vm.toDate).toBe('2020-01-01T00:00:00+00:00');
    });
});
