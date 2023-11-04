import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-script';
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
    url: '/search/product',
    status: 200,
    response: {
        data: [
            {
                attributes: {
                    id: 'p.a',
                    name: 'Product A',
                },
                id: 'p.a',
                relationships: [],
            },
            {
                attributes: {
                    id: 'p.b',
                    name: 'Product B',
                },
                id: 'p.b',
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

    return shallowMount(await Shopware.Component.build('sw-condition-script'), {
        stubs: {
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
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
            'sw-arrow-field': true,
            'sw-condition-type-select': true,
            'sw-icon': true,
            'sw-loader': true,
            'sw-label': true,
            'sw-highlight-text': {
                props: ['text'],
                template: '<div class="sw-highlight-text">{{ this.text }}</div>',
            },
            'sw-popover': {
                template: '<div class="sw-popover"><slot></slot></div>',
            },
            'sw-product-variant-info': {
                template: '<div class="sw-product-variant-info"><slot></slot></div>',
            },
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
        propsData: {
            condition,
        },
    });
}

describe('components/rule/condition-type/sw-condition-script', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render fields and set condition values on change', async () => {
        const wrapper = await createWrapper({
            type: 'scriptRule',
            scriptId: 'foo',
            appScriptCondition: {
                config: [{
                    name: 'operator',
                    type: 'select',
                    config: {
                        options: [
                            {
                                label: { 'en-GB': 'Is equal to' },
                                value: '=',
                            },
                            {
                                label: { 'en-GB': 'Is not equal to' },
                                value: '!=',
                            },
                        ],
                        validation: 'required',
                        componentName: 'sw-single-select',
                        customFieldType: 'select',
                        customFieldPosition: 1,
                    },
                }, {
                    name: 'firstName',
                    type: 'text',
                    config: {
                        type: 'text',
                        validation: 'required',
                        componentName: 'sw-field',
                        customFieldType: 'text',
                        customFieldPosition: 1,
                    },
                }, {
                    name: 'productIds',
                    type: 'entity',
                    config: {
                        validation: 'required',
                        componentName: 'sw-entity-multi-id-select',
                        customFieldType: 'select',
                        customFieldPosition: 1,
                        entity: 'product',
                    },
                }],
            },
        });
        await flushPromises();

        expect(wrapper.vm.condition.value.operator).toBeUndefined();
        expect(wrapper.vm.condition.value.firstName).toBeUndefined();
        expect(wrapper.vm.condition.value.productIds).toBeUndefined();
        expect(wrapper.vm.values.operator).toBeUndefined();
        expect(wrapper.vm.values.firstName).toBeUndefined();
        expect(wrapper.vm.values.productIds).toEqual([]);

        await wrapper.find('.sw-single-select .sw-select__selection').trigger('click');

        let entryOne = wrapper.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Is equal to');

        let entryTwo = wrapper.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Is not equal to');

        await entryTwo.trigger('click');

        expect(wrapper.vm.condition.value.operator).toBe('!=');
        expect(wrapper.vm.values.operator).toBe('!=');

        const input = wrapper.find('input[name=firstName]');
        await input.setValue('foobar');

        expect(wrapper.vm.condition.value.firstName).toBe('foobar');
        expect(wrapper.vm.values.firstName).toBe('foobar');

        await wrapper.find('.sw-entity-multi-select .sw-select__selection').trigger('click');
        await flushPromises();

        entryOne = wrapper.find('.sw-select-option--0');
        expect(entryOne.text()).toBe('Product A');

        entryTwo = wrapper.find('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Product B');

        await entryOne.trigger('click');
        await entryTwo.trigger('click');

        expect(wrapper.vm.condition.value.productIds).toEqual(['p.a', 'p.b']);
        expect(wrapper.vm.values.productIds).toEqual(['p.a', 'p.b']);
    });
});
