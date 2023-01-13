import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-generic';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/entity/sw-entity-multi-select';
import 'src/app/component/form/select/entity/sw-entity-multi-id-select';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/rule/sw-condition-unit-menu';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/rule/sw-arrow-field';
import ruleConditionsConfig from '../_mocks/ruleConditionsConfig.json';

const responses = global.repositoryFactoryMock.responses;

responses.addResponse({
    method: 'Post',
    url: '/search/currency',
    status: 200,
    response: {
        data: []
    }
});

responses.addResponse({
    method: 'Post',
    url: '/search/customer-group',
    status: 200,
    response: {
        data: [
            {
                attributes: {
                    id: 'g.a'
                },
                id: 'g.a',
                relationships: []
            },
            {
                attributes: {
                    id: 'g.b'
                },
                id: 'g.b',
                relationships: []
            }
        ],
        meta: {
            total: 2
        }
    }
});

async function createWrapper(condition = {}) {
    condition.getEntityName = () => 'rule_condition';

    return shallowMount(await Shopware.Component.build('sw-condition-generic'), {
        stubs: {
            'sw-condition-operator-select': await Shopware.Component.build('sw-condition-operator-select'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-select-base': await Shopware.Component.build('sw-select-base'),
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-multi-select': await Shopware.Component.build('sw-multi-select'),
            'sw-entity-multi-select': await Shopware.Component.build('sw-entity-multi-select'),
            'sw-entity-multi-id-select': await Shopware.Component.build('sw-entity-multi-id-select'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-select-selection-list': await Shopware.Component.build('sw-select-selection-list'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-condition-unit-menu': await Shopware.Component.build('sw-condition-unit-menu'),
            'sw-number-field': await Shopware.Component.build('sw-number-field'),
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
            'sw-arrow-field': await Shopware.Component.build('sw-arrow-field'),
            'sw-condition-type-select': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-label': true,
            'sw-highlight-text': true,
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>'
            },
            'sw-tagged-field': {
                template: '<div class="sw-tagged-field"></div>'
            }
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            ruleConditionsConfigApiService: {
                load: () => Promise.resolve()
            },
            availableTypes: {},
            availableGroups: [],
            childAssociationField: {},
            validationService: {},
            insertNodeIntoTree: () => ({}),
            removeNodeFromTree: () => ({}),
            createCondition: () => ({}),
            conditionScopes: [],
            unwrapAllLineItemsCondition: () => ({})
        },
        propsData: {
            condition
        },
        mixins: [Shopware.Mixin.getByName('generic-condition')]
    });
}

describe('components/rule/condition-type/sw-condition-generic', () => {
    beforeEach(() => {
        Shopware.State.commit('ruleConditionsConfig/setConfig', ruleConditionsConfig);
    });

    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render fields and set condition values on change', async () => {
        const wrapper = await createWrapper({
            type: 'customerCustomerGroup'
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBeUndefined();
        expect(wrapper.vm.condition.value.customerGroupIds).toBeUndefined();
        expect(wrapper.vm.values.customerGroupIds).toEqual([]);

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toEqual('!=');

        await wrapper.find('.sw-entity-multi-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await wrapper.find('.sw-select-option--1').trigger('click');

        expect(wrapper.vm.condition.value.customerGroupIds).toEqual(['g.a', 'g.b']);
        expect(wrapper.vm.values.customerGroupIds).toEqual(['g.a', 'g.b']);
    });

    it('should render condition with null operator', async () => {
        const wrapper = await createWrapper({
            type: 'customerShippingStreet'
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBeUndefined();
        expect(wrapper.vm.condition.value.streetName).toBeUndefined();

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--2').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toEqual('empty');
    });

    it('should render condition with bool value', async () => {
        const wrapper = await createWrapper({
            type: 'customerDifferentAddresses'
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeUndefined();

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeTruthy();

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.isDifferent).toBeFalsy();
    });

    it('should render condition with single select', async () => {
        const wrapper = await createWrapper({
            type: 'cartTaxDisplay'
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toBeUndefined();

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toEqual('gross');

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.taxDisplay).toEqual('net');
    });

    it('should render condition with tagged field', async () => {
        const wrapper = await createWrapper({
            type: 'customerCustomerNumber'
        });
        await flushPromises();

        expect(wrapper.find('.sw-tagged-field')).not.toBeUndefined();
    });

    it('should render condition with custom operators', async () => {
        const wrapper = await createWrapper({
            type: 'conditionWithCustomOperators'
        });
        await flushPromises();

        expect(wrapper.vm.condition.value).toBeUndefined();

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toEqual('foo');

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');
        await wrapper.find('.sw-select-option--1').trigger('click');
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toEqual('bar');
    });

    it('should render unit menu when condition has unit', async () => {
        const wrapper = await createWrapper(
            {
                type: 'cartLineItemDimensionWeight'
            }
        );
        await flushPromises();

        const menu = wrapper.find('.sw-condition-generic__unit-menu');
        expect(menu.props().type).toEqual('weight');
        expect(menu.exists()).toBeTruthy();
    });

    it('should be possible to enter a new value into the input when the base value is not selected', async () => {
        const wrapper = await createWrapper({
            type: 'cartLineItemDimensionWeight'
        });

        // set a base value
        const unitInput = wrapper.find('#sw-field--amount');
        await unitInput.setValue('10');
        await unitInput.trigger('change');

        // change the unit
        const unitMenu = wrapper.find('.sw-condition-unit-menu');
        await unitMenu.trigger('click');

        const unitOption = wrapper.findAll('.sw-condition-unit-menu__menu-item').at(2);
        await unitOption.trigger('click');

        await unitInput.setValue('10000');
        await unitInput.trigger('change');

        expect(unitInput.element.value).toEqual('10000');
    });
});
