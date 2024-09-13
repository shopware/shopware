import { mount } from '@vue/test-utils';

import flowState from 'src/module/sw-flow/state/flow.state';

/**
 * @package services-settings
 */

const fieldClasses = [
    '.sw-flow-set-entity-custom-field-modal__custom-field-set',
    '.sw-flow-set-entity-custom-field-modal__custom-field',
];

const customNormalField = {
    id: 'field1',
    config: {
        type: 'number',
        label: { 'en-GB': 'consequatur maxime illo' },
        numberType: 'int',
        placeholder: { 'en-GB': 'Type a number...' },
        componentName: 'sw-field',
        customFieldType: 'number',
        customFieldPosition: 1,
    },
};

const customMultipleField = {
    id: 'field2',
    config: {
        label: { 'en-GB': 'Select field' },
        options: [
            { label: { 'en-GB': 'Option 1' }, value: 'op_1' },
            { label: { 'en-GB': 'Option 2' }, value: 'op_2' },
            { label: { 'en-GB': 'Option 3' }, value: 'op_3' },
        ],
        helpText: { 'en-GB': null },
        placeholder: { 'en-GB': null },
        componentName: 'sw-multi-select',
        customFieldType: 'select',
        customFieldPosition: 1,
    },
};

async function createWrapper(customField = customNormalField) {
    return mount(await wrapTestComponent('sw-flow-set-entity-custom-field-modal', { sync: true }), {
        global: {
            provide: {
                flowBuilderService: {
                    getActionModalName: () => {},
                },
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'custom_field_set') {
                            return { search: () => Promise.resolve([{ id: 'set1', config: { label: { 'en-GB': 'Electronics' } } }]) };
                        }

                        if (entity === 'custom_field') {
                            return { search: () => Promise.resolve(
                                [customField],
                            ) };
                        }

                        if (entity === 'currency') {
                            return { get: () => Promise.resolve({ id: '' }) };
                        }

                        return { search: () => Promise.resolve() };
                    },
                },
            },
            mocks: {
                $tc: (...args) => JSON.stringify([...args]),
            },
            data() {
                return {
                    optionUpsert: {
                        id: 'upsert',
                        name: 'Upsert',
                    },
                    optionCreate: {
                        id: 'create',
                        name: '3',
                    },
                    optionClear: {
                        id: 'clear',
                        name: '3',
                    },
                    optionAdd: {
                        id: 'add',
                        name: '4',
                    },
                    optionRemove: {
                        id: 'remove',
                        name: '5',
                    },
                    fieldOptionSelected: 'upsert',
                    fieldOptions: [
                        {
                            id: 'upsert',
                            name: 'Upsert',
                        },
                    ],
                };
            },
            stubs: {
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-select-field': {
                    template: '<div class="sw-select-field"></div>',
                },
                'sw-modal': {
                    template: `
                        <div class="sw-modal">
                            <slot name="modal-header"></slot>
                            <slot></slot>
                            <slot name="modal-footer"></slot>
                        </div>
                    `,
                },
                'sw-button': {
                    template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                },
                'sw-loader': true,
                'sw-label': true,
                'sw-icon': true,
                'sw-highlight-text': true,
                'sw-field': true,
                'sw-multi-select': true,
                'sw-single-select': true,
                'sw-product-variant-info': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
        },
        props: {
            sequence: {},
        },
    });
}

describe('module/sw-flow/component/sw-flow-set-entity-custom-field-modal', () => {
    beforeAll(() => {
        Shopware.Service().register('flowBuilderService', () => {
            return {
                mapActionType: () => {},

                getAvailableEntities: () => {
                    return [
                        {
                            label: 'Order',
                            value: 'order',
                        },
                        {
                            label: 'Customer',
                            value: 'customer',
                        },
                    ];
                },
            };
        });
    });

    Shopware.State.registerModule('swFlowState', {
        ...flowState,
        state: {
            triggerEvent: {
                data: {
                    order: {
                        type: 'entity',
                    },
                },
                customerAware: false,
                extensions: [],
                logAware: false,
                mailAware: true,
                name: 'checkout.order.place',
                orderAware: true,
                salesChannelAware: true,
                userAware: false,
                webhookAware: true,
            },
            customFieldSets: [],
            customFields: [],
        },
    });

    it('should show these fields on modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        fieldClasses.forEach(elementClass => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it('should show error if custom field set empty', async () => {
        const wrapper = await createWrapper();

        const buttonSave = wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button');
        await buttonSave.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-set').classes())
            .toContain('has--error');
    });

    it('should show error if custom field select entity empty', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.entity = null;

        const buttonSave = wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button');
        await buttonSave.trigger('click');

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__entity-field').attributes('error'))
            .toBeTruthy();
    });

    it('should show error if custom field empty', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const selection = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-set');
        await selection.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const buttonSave = wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button');
        await buttonSave.trigger('click');

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field').classes())
            .toContain('has--error');
    });

    it('should show normal options select and value select', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const selectionSet = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-set');
        await selectionSet.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const selectionField = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field');
        await selectionField.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const valueOption = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-value-options');
        expect(valueOption.attributes().disabled).toBeFalsy();

        expect(wrapper.vm.fieldOptions).toHaveLength(3);

        wrapper.vm.fieldOptions.forEach((option) => {
            expect(['upsert', 'create', 'clear']).toContain(option.value);
        });

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-value')
            .attributes().disabled).toBeFalsy();
    });

    it('should show multiple options select and value select', async () => {
        const wrapper = await createWrapper(customMultipleField);
        await flushPromises();

        const selectionSet = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-set');
        await selectionSet.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const selectionField = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field');
        await selectionField.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const valueOption = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-value-options');
        expect(valueOption.attributes().disabled).toBeFalsy();

        expect(wrapper.vm.fieldOptions).toHaveLength(5);

        wrapper.vm.fieldOptions.forEach((option) => {
            expect(['upsert', 'create', 'clear', 'add', 'remove']).toContain(option.value);
        });

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-value')
            .attributes().disabled).toBeFalsy();
    });

    it('should save action', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const selectionSet = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-set');
        await selectionSet.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        const selectionField = wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field');
        await selectionField.find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-flow-set-entity-custom-field-modal__custom-field-value')
            .attributes().disabled).toBeFalsy();

        const buttonSave = wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button');
        await buttonSave.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['process-finish'][0]).toEqual([{
            config: {
                entity: 'order',
                customFieldSetId: 'set1',
                customFieldId: 'field1',
                customFieldValue: null,
                option: 'upsert',
                optionLabel: '[\"sw-flow.modals.setEntityCustomField.options.overwrite\"]',
            },
        }]);
    });

    it('should not able to show error message when input is refilled', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button').trigger('click');
        expect(wrapper.find(fieldClasses[0]).classes()).toContain('has--error');

        await wrapper.find(fieldClasses[0]).find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.find(fieldClasses[0]).classes()).not.toContain('has--error');

        await wrapper.find('.sw-flow-set-entity-custom-field-modal__save-button').trigger('click');

        expect(wrapper.find(fieldClasses[1]).classes()).toContain('has--error');

        await wrapper.find(fieldClasses[1]).find('.sw-select__selection').trigger('click');
        await flushPromises();

        await wrapper.find('.sw-select-option--0').trigger('click');
        await flushPromises();

        expect(wrapper.find(fieldClasses[1]).classes()).not.toContain('has--error');
    });

    it('should show correctly the entity options', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.entityOptions).toHaveLength(2);
        wrapper.vm.entityOptions.forEach((option) => {
            expect(['Order', 'Customer']).toContain(option.label);
        });
    });
});
