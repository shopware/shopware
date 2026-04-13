/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import EntityCollection from 'src/core/data/entity-collection.data';

const defaultCustomFieldMock = [
    {
        id: 'custom-field-id-1',
        name: 'custom_test_checkbox',
        type: 'bool',
        active: true,
        customFieldSetId: 'custom-field-set-id-1',
        allowCustomerWrite: false,
        allowCartExpose: true,
        storeApiAware: true,
        config: {
            type: 'checkbox',
            label: 'checkbox',
            componentName: 'sw-field',
            customFieldType: 'checkbox',
        },
        customFieldSet: {
            config: {
                label: 'custom-field-set-label-1',
            },
        },
    },
    {
        id: 'custom-field-id-2',
        name: 'custom_test_switch',
        type: 'bool',
        active: true,
        customFieldSetId: 'custom-field-set-id-2',
        allowCustomerWrite: false,
        allowCartExpose: true,
        storeApiAware: true,
        config: {
            type: 'switch',
            label: 'switch',
            componentName: 'sw-field',
            customFieldType: 'switch',
        },
        customFieldSet: {
            config: {
                label: 'custom-field-set-label-2',
            },
        },
    },
    {
        id: 'custom-field-id-3',
        name: 'custom_test_editor',
        type: 'html',
        active: true,
        customFieldSetId: 'custom-field-set-id-3',
        allowCustomerWrite: false,
        allowCartExpose: true,
        storeApiAware: true,
        config: {
            label: 'editor',
            componentName: 'sw-text-editor',
            customFieldType: 'textEditor',
        },
        customFieldSet: {
            config: {
                label: 'custom-field-set-label-3',
            },
        },
    },
    {
        id: 'custom-field-id-4',
        name: 'custom_test_text',
        type: 'text',
        active: true,
        customFieldSetId: 'custom-field-set-id-4',
        allowCustomerWrite: false,
        allowCartExpose: true,
        storeApiAware: true,
        config: {
            label: 'text',
            componentName: 'sw-field',
            customFieldType: 'text',
        },
        customFieldSet: {
            config: {
                label: 'custom-field-set-label-4',
            },
        },
    },
];

const defaultProps = {
    condition: {
        id: 'rule-condition-id',
        type: 'cartLineItemCustomField',
        ruleId: 'rule-id',
        customFields: null,
        children: [],
        value: {
            operator: null,
            renderedField: null,
            selectedField: null,
            selectedFieldSet: null,
            renderedFieldValue: null,
        },
    },
};

const getRepositoryFactoryMock = (mock) => ({
    search: jest.fn(() => Promise.resolve(new EntityCollection('/custom-field', 'custom_field', null, {}, mock, 2, null))),
    get: jest.fn(() => Promise.resolve()),
});

async function createWrapper(props = defaultProps, customFieldMock = defaultCustomFieldMock) {
    return mount(
        await wrapTestComponent('sw-condition-line-item-custom-field', {
            sync: true,
        }),
        {
            props,
            global: {
                stubs: {
                    'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                    'sw-select-base': await wrapTestComponent('sw-select-base'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                    'sw-popover': await wrapTestComponent('sw-popover'),
                    'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated'),
                    'sw-select-result': await wrapTestComponent('sw-select-result'),
                    'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                    'sw-single-select': await wrapTestComponent('sw-single-select'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-highlight-text': true,
                    'sw-help-text': true,
                    'mt-icon': true,
                    'sw-product-variant-info': true,
                    'sw-inheritance-switch': true,
                    'sw-loader': true,
                    'sw-ai-copilot-badge': true,
                    'sw-condition-type-select': true,
                    'sw-field-error': true,
                    'sw-context-button': true,
                    'sw-context-menu': true,
                    'sw-context-menu-item': true,
                    'mt-floating-ui': true,
                },
                provide: {
                    conditionDataProviderService: new ConditionDataProviderService(),
                    availableTypes: {},
                    availableGroups: [],
                    restrictedConditions: [],
                    childAssociationField: {},
                    validationService: {},
                    insertNodeIntoTree: () => ({}),
                    removeNodeFromTree: () => ({}),
                    createCondition: () => ({}),
                    conditionScopes: [],
                    unwrapAllLineItemsCondition: () => ({}),
                    repositoryFactory: {
                        create: (type) => {
                            if (type === 'custom_field') {
                                return getRepositoryFactoryMock(customFieldMock);
                            }

                            return {
                                get: () => Promise.resolve(),
                            };
                        },
                    },
                },
            },
        },
    );
}

describe('components/rule/condition-type/sw-condition-line-item-custom-field', () => {
    it.each([
        {
            name: 'default',
            customField: { ...defaultCustomFieldMock[0], allowCartExpose: false },
            expected: 'global.sw-condition.condition.lineItemCustomField.field.customFieldSelect.tooltip',
        },
        { name: 'cart expose', customField: { ...defaultCustomFieldMock[0], allowCartExpose: true }, expected: '' },
    ])('should render custom field tooltip: $name', async ({ customField, expected }) => {
        const wrapper = await createWrapper(defaultProps, [
            customField,
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-result').attributes('tooltip-mock-message')).toBe(expected);
    });

    it.each([
        {
            name: 'label',
            customFieldSet: {
                config: {
                    label: 'This is a very long text that should be truncated.',
                },
            },
        },
        {
            name: 'name',
            customFieldSet: {
                name: 'This is a very long text that should be truncated.',
                config: {
                    label: null,
                },
            },
        },
    ])('should truncate field description if too long: $name', async ({ customFieldSet }) => {
        const wrapper = await createWrapper(defaultProps, [
            {
                ...defaultCustomFieldMock[0],
                customFieldSet: {
                    ...defaultCustomFieldMock[0].customFieldSet,
                    ...customFieldSet,
                },
            },
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-select-result__result-item-description').text()).toBe('This is a very lo...');
    });

    it('should update field & reset field on change if unselected', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('checkbox');
        expect(wrapper.find('.sw-condition-operator-select').exists()).toBe(true);

        await wrapper.find('.sw-select__select-indicator-hitbox').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-condition-operator-select').exists()).toBe(false);
    });

    it.each([
        { name: 'checkboxes', customField: defaultCustomFieldMock[0], label: 'checkbox' },
        { name: 'switches', customField: defaultCustomFieldMock[1], label: 'switch' },
    ])('should transform custom field config & operators for: $name', async ({ customField, label }) => {
        const wrapper = await createWrapper(defaultProps, [
            customField,
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe(label);
        expect(wrapper.find('.sw-condition-operator-select').exists()).toBe(true);

        await wrapper.find('.sw-condition-operator-select .sw-select__selection').trigger('click');
        await flushPromises();

        const operators = wrapper.findAll('.sw-condition-operator-select .sw-select-result');
        expect(operators).toHaveLength(1);

        expect(operators[0].find('sw-highlight-text-stub').attributes('text')).toBe('global.sw-condition.operator.equals');

        await operators[0].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-condition-operator-select .sw-single-select__selection-text').text()).toBe(
            'global.sw-condition.operator.equals',
        );

        await wrapper.find('.sw-form-field-renderer .sw-select__selection').trigger('click');
        await flushPromises();

        const options = wrapper.findAll('.sw-form-field-renderer .sw-select-result');
        expect(options).toHaveLength(2);

        expect(options[0].find('sw-highlight-text-stub').attributes('text')).toBe('global.default.yes');
        expect(options[1].find('sw-highlight-text-stub').attributes('text')).toBe('global.default.no');
    });

    it('should transform custom field config & operators for text editors', async () => {
        const wrapper = await createWrapper(defaultProps, [
            defaultCustomFieldMock[2],
        ]);
        await flushPromises();

        await wrapper.find('.sw-entity-single-select .sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-result').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-single-select__selection-text').text()).toBe('editor');
        expect(wrapper.find('.sw-condition-operator-select').exists()).toBe(true);

        await wrapper.find('.sw-condition-operator-select .sw-select__selection').trigger('click');
        await flushPromises();

        const operators = wrapper.findAll('.sw-condition-operator-select .sw-select-result');
        expect(operators).toHaveLength(2);

        expect(operators[0].find('sw-highlight-text-stub').attributes('text')).toBe('global.sw-condition.operator.equals');
        expect(operators[1].find('sw-highlight-text-stub').attributes('text')).toBe(
            'global.sw-condition.operator.notEquals',
        );

        await operators[0].trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-condition-operator-select .sw-single-select__selection-text').text()).toBe(
            'global.sw-condition.operator.equals',
        );

        const field = wrapper.find('.sw-form-field-renderer');
        expect(field.find('label').text()).toBe('editor');
        expect(field.attributes('componentname')).toBe('sw-field');
        expect(field.attributes('type')).toBe('text');
    });
});
