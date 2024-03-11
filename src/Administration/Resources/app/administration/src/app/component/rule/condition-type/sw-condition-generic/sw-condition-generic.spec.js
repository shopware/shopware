import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import ruleConditionsConfig from '../_mocks/ruleConditionsConfig.json';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/currency',
    status: 200,
    response: {
        data: [],
    },
});

responses.addResponse({
    method: 'Post',
    url: '/search/customer-group',
    status: 200,
    response: {
        data: [
            {
                attributes: {
                    id: 'g.a',
                },
                id: 'g.a',
                relationships: [],
            },
            {
                attributes: {
                    id: 'g.b',
                },
                id: 'g.b',
                relationships: [],
            },
        ],
        meta: {
            total: 2,
        },
    },
});

async function createWrapper(condition = {}) {
    condition.getEntityName = () => 'rule_condition';

    return mount(
        await wrapTestComponent('sw-condition-generic', { sync: true }),
        {
            attachTo: document.body,
            props: {
                condition,
            },
            global: {
                renderStubDefaultSlot: true,
                stubs: {
                    'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                    'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                    'sw-entity-multi-id-select': await wrapTestComponent('sw-entity-multi-id-select'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-condition-unit-menu': await wrapTestComponent('sw-condition-unit-menu', { sync: true }),
                    'sw-number-field': await wrapTestComponent('sw-number-field'),
                    'sw-context-button': true,
                    'sw-context-menu-item': true,
                    'sw-field-error': true,
                    'sw-condition-type-select': true,
                    'sw-icon': true,
                    'sw-loader': true,
                    'sw-label': true,
                    'sw-highlight-text': true,
                    'sw-popover': {
                        template: '<div class="sw-popover"><slot></slot></div>',
                    },
                    'sw-tagged-field': {
                        template: '<div class="sw-tagged-field"></div>',
                    },
                },
                provide: {
                    conditionDataProviderService: new ConditionDataProviderService(),
                    ruleConditionsConfigApiService: {
                        load: () => Promise.resolve(),
                    },
                    availableTypes: [],
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
        },
    );
}

describe('components/rule/condition-type/sw-condition-generic', () => {
    beforeEach(() => {
        Shopware.State.commit('ruleConditionsConfig/setConfig', ruleConditionsConfig);
    });

    it('should render fields and set condition values on change', async () => {
        const wrapper = await createWrapper({
            type: 'customerCustomerGroup',
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBeUndefined();
        expect(wrapper.vm.condition.value.customerGroupIds).toBeUndefined();
        expect(wrapper.vm.values.customerGroupIds).toEqual([]);

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--1').trigger('click');

        expect(wrapper.vm.condition.value.operator).toBe('!=');

        await wrapper.get('.sw-entity-multi-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--0').trigger('click');
        await wrapper.get('.sw-select-option--1').trigger('click');

        expect(wrapper.vm.condition.value.customerGroupIds).toEqual(expect.arrayContaining(['g.a', 'g.b']));
        expect(wrapper.vm.values.customerGroupIds).toEqual(expect.arrayContaining(['g.a', 'g.b']));
    });

    it('should render condition with null operator', async () => {
        const wrapper = await createWrapper({
            type: 'customerShippingStreet',
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBeUndefined();
        expect(wrapper.vm.condition.value.streetName).toBeUndefined();

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--2').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBe('empty');
    });

    it('should render condition with bool value', async () => {
        const wrapper = await createWrapper({
            type: 'customerDifferentAddresses',
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeUndefined();

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeTruthy();

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.get('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeFalsy();
    });

    it('should render condition with single select', async () => {
        const wrapper = await createWrapper({
            type: 'cartTaxDisplay',
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toBeUndefined();

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toBe('gross');

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.get('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toBe('net');
    });

    it('should render condition with tagged field', async () => {
        const wrapper = await createWrapper({
            type: 'customerCustomerNumber',
        });
        await flushPromises();

        expect(wrapper.get('.sw-tagged-field')).toBeDefined();
    });

    it('should render condition with custom operators', async () => {
        const wrapper = await createWrapper({
            type: 'conditionWithCustomOperators',
        });
        await flushPromises();

        expect(wrapper.vm.condition.value).toBeUndefined();

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.get('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBe('foo');

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.get('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBe('bar');
    });

    it('should render unit menu when condition has unit', async () => {
        const wrapper = await createWrapper(
            {
                type: 'cartLineItemDimensionWeight',
            },
        );

        const menu = wrapper.getComponent('.sw-condition-generic__unit-menu');

        expect(menu.exists()).toBeTruthy();
        expect(menu.props('type')).toBe('weight');
    });

    it('should be possible to enter a new value into the input when the base value is not selected', async () => {
        const wrapper = await createWrapper({
            type: 'cartLineItemDimensionWeight',
        });
        await flushPromises();

        // set a base value
        const unitInput = wrapper.get('#sw-field--amount');
        await unitInput.setValue('10');
        await unitInput.trigger('change');

        // change the unit
        const unitMenu = wrapper.get('.sw-condition-unit-menu');
        await unitMenu.trigger('click');

        const unitOption = wrapper.findAll('.sw-condition-unit-menu__menu-item').at(2);
        await unitOption.trigger('click');

        await unitInput.setValue('10000');
        await unitInput.trigger('change');

        expect(unitInput.element.value).toBe('10000');
    });
});
