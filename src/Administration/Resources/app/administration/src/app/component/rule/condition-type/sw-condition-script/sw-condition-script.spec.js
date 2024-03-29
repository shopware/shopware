import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

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

    return mount(await wrapTestComponent('sw-condition-script', { sync: true }), {
        props: {
            condition,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
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
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
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
        },
    });
}

describe('components/rule/condition-type/sw-condition-script', () => {
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

        await wrapper.get('.sw-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        let entryOne = wrapper.get('.sw-select-option--0');
        expect(entryOne.text()).toBe('Is equal to');

        let entryTwo = wrapper.get('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Is not equal to');

        await entryTwo.trigger('click');

        expect(wrapper.vm.condition.value.operator).toBe('!=');
        expect(wrapper.vm.values.operator).toBe('!=');

        const input = wrapper.get('input[name=firstName]');
        await input.setValue('foobar');

        expect(wrapper.vm.condition.value.firstName).toBe('foobar');
        expect(wrapper.vm.values.firstName).toBe('foobar');

        await wrapper.get('.sw-entity-multi-select .sw-select__selection').trigger('click');
        await flushPromises();

        entryOne = wrapper.get('.sw-select-option--0');
        expect(entryOne.text()).toBe('Product A');

        entryTwo = wrapper.get('.sw-select-option--1');
        expect(entryTwo.text()).toBe('Product B');

        await entryOne.trigger('click');
        await entryTwo.trigger('click');

        expect(wrapper.vm.condition.value.productIds).toEqual(expect.arrayContaining(['p.a', 'p.b']));
        expect(wrapper.vm.values.productIds).toEqual(expect.arrayContaining(['p.a', 'p.b']));
    });
});
