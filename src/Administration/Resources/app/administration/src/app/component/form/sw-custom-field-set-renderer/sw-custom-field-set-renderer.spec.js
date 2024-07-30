/**
 * @package admin
 * @group disabledCompat
 */

/* eslint-disable max-len */
import { mount } from '@vue/test-utils';
import uuid from 'src/../test/_helper_/uuid';

function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

async function createWrapper(props) {
    return mount(await wrapTestComponent('sw-custom-field-set-renderer', {
        sync: true,
    }), {
        props,
        global: {
            stubs: {
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-label': await wrapTestComponent('sw-label'),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                'sw-inheritance-switch': await wrapTestComponent('sw-inheritance-switch'),
                'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer', {
                    sync: true,
                }),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                'sw-number-field': await wrapTestComponent('sw-number-field'),
                'sw-number-field-deprecated': await wrapTestComponent('sw-number-field-deprecated', { sync: true }),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-entity-multi-select': true,
                'sw-block-field': await wrapTestComponent('sw-block-field', {
                    sync: true,
                }),
                'sw-base-field': await wrapTestComponent('sw-base-field', {
                    sync: true,
                }),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-icon': {
                    template: '<div class="sw-icon" @click="$emit(\'click\', $event)"></div>',
                    inheritAttrs: false,
                },
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-media-field': await wrapTestComponent('sw-media-field'),
                'sw-media-media-item': await wrapTestComponent('sw-media-media-item'),
                'sw-media-base-item': await wrapTestComponent('sw-media-base-item'),
                'sw-media-preview-v2': await wrapTestComponent('sw-media-preview-v2'),
                'sw-colorpicker': await wrapTestComponent('sw-text-field'),
                'sw-upload-listener': true,
                'sw-simple-search-field': true,
                'sw-loader': true,
                'sw-datepicker': await wrapTestComponent('sw-text-field'),
                'sw-text-editor': {
                    props: ['value'],
                    template: '<input type="text" :value="value" @change="$emit(\'update:value\', $event.target.value)"/>',
                },
                'sw-skeleton': await wrapTestComponent('sw-skeleton'),
                'sw-skeleton-bar': await wrapTestComponent('sw-skeleton-bar'),
                'sw-entity-single-select': await wrapTestComponent('sw-entity-single-select'),
                'sw-button-process': true,
                'sw-media-collapse': true,
                'mt-tabs': true,
                'sw-extension-component-section': true,
                'router-link': true,
                'sw-help-text': true,
                'mt-text-field': true,
                'sw-field-copyable': true,
                'sw-ai-copilot-badge': true,
                'mt-skeleton-bar': true,
                'sw-skeleton-bar-deprecated': true,
                'mt-number-field': true,
                'mt-floating-ui': true,
                'sw-color-badge': true,
                'sw-media-upload-v2': true,
                'sw-pagination': true,
                'mt-button': true,
                'sw-context-menu-item': true,
                'sw-media-modal-replace': true,
                'sw-media-modal-delete': true,
                'sw-media-modal-move': true,
                'sw-context-button': true,
                'mt-switch': true,
                'mt-checkbox': true,
                'sw-product-variant-info': true,
            },
            provide: {
                repositoryFactory: {
                    create: (entity) => ({
                        search: () => {
                            if (entity === 'media') {
                                return Promise.resolve([
                                    {
                                        hasFile: true,
                                        fileName: 'media_after',
                                        fileExtension: 'jpg',
                                        id: uuid.get('media after'),
                                    },
                                    {
                                        hasFile: true,
                                        fileName: 'media_before',
                                        fileExtension: 'jpg',
                                        id: uuid.get('media before'),
                                    },
                                ]);
                            }

                            if (entity === 'country') {
                                return Promise.resolve([
                                    {
                                        id: uuid.get('Germany'),
                                        name: 'Germany',
                                    },
                                    {
                                        id: uuid.get('Vietnam'),
                                        name: 'Vietnam',
                                    },
                                ]);
                            }

                            return Promise.resolve('bar');
                        },
                        get: (id) => {
                            if (entity === 'media') {
                                if (id === uuid.get('media before')) {
                                    return Promise.resolve({
                                        hasFile: true,
                                        fileName: 'media_before',
                                        fileExtension: 'jpg',
                                        id: uuid.get('media before'),
                                    });
                                }

                                if (id === uuid.get('media after')) {
                                    return Promise.resolve({
                                        hasFile: true,
                                        fileName: 'media_after',
                                        fileExtension: 'jpg',
                                        id: uuid.get('media after'),
                                    });
                                }
                            }

                            if (id === uuid.get('custom_sports')) {
                                return Promise.resolve({
                                    id: uuid.get('custom_sports'),
                                    name: 'custom_sports',
                                    position: 1,
                                    config: { label: { 'en-GB': 'Sports' } },
                                    customFields: [
                                        {
                                            active: true,
                                            name: 'custom_sports_football',
                                            type: 'text',
                                            config: {
                                                customFieldPosition: 1,
                                                customFieldType: 'text',
                                                componentName: 'sw-field',
                                                type: 'text',
                                            },
                                        },
                                        {
                                            active: true,
                                            name: 'custom_sports_score',
                                            type: 'float',
                                            config: {
                                                type: 'number',
                                                label: { 'en-GB': 'qui et vel' },
                                                numberType: 'float',
                                                placeholder: { 'en-GB': 'Type a floating point number...' },
                                                componentName: 'sw-field',
                                                customFieldType: 'number',
                                                customFieldPosition: 1,
                                            },
                                        },
                                    ],
                                });
                            }

                            if (entity === 'country') {
                                if (id === uuid.get('Germany')) {
                                    return Promise.resolve({
                                        id: uuid.get('Germany'),
                                        name: 'Germany',
                                    });
                                }

                                if (id === uuid.get('Vietnam')) {
                                    return Promise.resolve({
                                        id: uuid.get('Vietnam'),
                                        name: 'Vietnam',
                                    });
                                }
                            }

                            if (id === uuid.get('custom_clothing')) {
                                return Promise.resolve({
                                    id: uuid.get('custom_clothing'),
                                    name: 'custom_clothing',
                                    position: 1,
                                    config: { label: { 'en-GB': 'Clothing' } },
                                    customFields: [
                                        {
                                            active: true,
                                            name: 'custom_clothing_name',
                                            type: 'text',
                                            config: {
                                                customFieldPosition: 1,
                                                customFieldType: 'text',
                                                componentName: 'sw-field',
                                                type: 'text',
                                            },
                                        },
                                    ],
                                });
                            }

                            return Promise.resolve({});
                        },
                    }),
                },
                validationService: {},
                mediaService: {},
                systemConfigApiService: {
                    getValues: () => {
                        return Promise.resolve({});
                    },
                },
            },
        },
    });
}

describe('src/app/component/form/sw-custom-field-set-renderer', () => {
    /** @type Wrapper */
    let wrapper;

    const configuredFields = [
        {
            testFieldLabel: 'single select',
            customFieldType: 'select',
            customFieldConfigType: 'select',
            fieldName: 'custom_first_tab_i_am_a_single_select',
            entityCustomFieldValueBefore: 'first_choice',
            entityCustomFieldValueAfter: 'second_choice',
            componentName: 'sw-single-select',
            componentLabel: 'I am a single select field',
            componentConfigAddition: {
                options: [
                    { label: { 'en-GB': 'First choice' }, value: 'first_choice' },
                    { label: { 'en-GB': 'Second choice' }, value: 'second_choice' },
                ],
            },
            domFallbackValue: '',
            fallbackValue: [],
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.text()).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: '.sw-single-select__selection-text',
            domFieldValueBefore: 'First choice',
            domFieldValueSelectorAfter: '.sw-single-select__selection-text',
            domFieldValueAfter: 'Second choice',
            changeValueFunction: async (customField) => {
                // open select field
                await customField.find('.sw-select__selection').trigger('click');
                await flushPromises();

                // check if second option exists
                const secondChoiceOption = customField.find('.sw-select-option--second_choice');
                expect(secondChoiceOption.isVisible()).toBe(true);

                // click on second option
                await secondChoiceOption.trigger('click');
            },
        },
        {
            testFieldLabel: 'multi select',
            customFieldType: 'select',
            customFieldConfigType: 'select',
            fieldName: 'custom_first_tab_i_am_a_multi_select',
            entityCustomFieldValueBefore: ['first_choice'],
            entityCustomFieldValueAfter: ['first_choice', 'second_choice'],
            componentName: 'sw-multi-select',
            componentLabel: 'I am a multi select field',
            componentConfigAddition: {
                options: [
                    { label: { 'en-GB': 'First choice' }, value: 'first_choice' },
                    { label: { 'en-GB': 'Second choice' }, value: 'second_choice' },
                ],
            },
            domFallbackValue: '',
            fallbackValue: [],
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                if (domFieldValueBefore.length <= 0) {
                    expect(domFieldValue.exists()).toBe(false);
                } else {
                    expect(domFieldValue.text()).toBe(domFieldValueBefore);
                }
            },
            domFieldValueSelectorBefore: '.sw-select-selection-list__item-holder--0',
            domFieldValueBefore: 'First choice',
            domFieldValueSelectorAfter: '.sw-select-selection-list__item-holder--1',
            domFieldValueAfter: 'Second choice',
            changeValueFunction: async (customField) => {
                // open select field
                await customField.find('.sw-select__selection').trigger('click');
                await flushPromises();

                // check if second option exists
                const secondChoiceOption = customField.find('.sw-select-option--second_choice');
                expect(secondChoiceOption.isVisible()).toBe(true);

                // click on second option
                await secondChoiceOption.trigger('click');
            },
        },
        {
            testFieldLabel: 'text field',
            customFieldType: 'text',
            customFieldConfigType: 'text',
            fieldName: 'custom_first_tab_i_am_a_text_field',
            entityCustomFieldValueBefore: 'Alpha',
            entityCustomFieldValueAfter: 'Beta',
            componentName: 'sw-field',
            componentLabel: 'I am a text field',
            componentConfigAddition: {},
            domFallbackValue: '',
            fallbackValue: '',
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="text"]',
            domFieldValueBefore: 'Alpha',
            domFieldValueSelectorAfter: 'input[type="text"]',
            domFieldValueAfter: 'Beta',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="text"]').setValue('Beta');
            },
        },
        {
            testFieldLabel: 'media field',
            customFieldType: 'text',
            customFieldConfigType: 'media',
            fieldName: 'custom_first_tab_i_am_a_media_field',
            entityCustomFieldValueBefore: uuid.get('media before'),
            entityCustomFieldValueAfter: uuid.get('media after'),
            componentName: 'sw-media-field',
            componentLabel: 'I am a media field',
            componentConfigAddition: {},
            domFallbackValue: '',
            fallbackValue: '',
            domFieldValueSelectorExpectation: async (domFieldValue, domFieldValueBefore) => {
                if (domFieldValueBefore.length <= 0) {
                    expect(domFieldValue.exists()).toBe(false);
                } else {
                    expect(domFieldValue.text()).toBe(domFieldValueBefore);
                }
            },
            domFieldValueSelectorBefore: '.sw-media-base-item__name',
            domFieldValueBefore: 'media_before.jpg',
            domFieldValueSelectorAfter: '.sw-media-base-item__name',
            domFieldValueAfter: 'media_after.jpg',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('.sw-media-field__toggle-button').trigger('click');
                await flushPromises();
                await customField.find('.sw-media-field__suggestion-list-entry:first-child .sw-media-base-item').trigger('click');
            },
        },
        {
            testFieldLabel: 'number field int',
            customFieldType: 'int',
            customFieldConfigType: 'number',
            fieldName: 'custom_first_tab_i_am_a_number_field',
            entityCustomFieldValueBefore: 23,
            entityCustomFieldValueAfter: 49,
            componentName: 'sw-field',
            componentLabel: 'I am a number field',
            componentConfigAddition: {},
            domFallbackValue: '0',
            fallbackValue: 0,
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="text"]',
            domFieldValueBefore: '23',
            domFieldValueSelectorAfter: 'input[type="text"]',
            domFieldValueAfter: '49',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="text"]').setValue(49);
                await customField.find('input[type="text"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'number field float',
            customFieldType: 'float',
            customFieldConfigType: 'number',
            fieldName: 'custom_first_tab_i_am_a_number_field',
            entityCustomFieldValueBefore: 23,
            entityCustomFieldValueAfter: 49,
            componentName: 'sw-field',
            componentLabel: 'I am a number field',
            componentConfigAddition: {},
            domFallbackValue: '0',
            fallbackValue: 0,
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="text"]',
            domFieldValueBefore: '23',
            domFieldValueSelectorAfter: 'input[type="text"]',
            domFieldValueAfter: '49',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="text"]').setValue(49);
                await customField.find('input[type="text"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'datetime field',
            customFieldType: 'datetime',
            customFieldConfigType: 'date',
            fieldName: 'custom_first_tab_i_am_a_datetime_field',
            entityCustomFieldValueBefore: '2020-01-02T12:00:00+00:00',
            entityCustomFieldValueAfter: '2021-01-02T12:00:00+00:00',
            componentName: 'sw-field',
            componentLabel: 'I am a datetime field',
            componentConfigAddition: {},
            domFallbackValue: '',
            fallbackValue: '',
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="text"]',
            domFieldValueBefore: '2020-01-02T12:00:00+00:00',
            domFieldValueSelectorAfter: 'input[type="text"]',
            domFieldValueAfter: '2021-01-02T12:00:00+00:00',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="text"]').setValue('2021-01-02T12:00:00+00:00');
                await customField.find('input[type="text"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'checkbox field',
            customFieldType: 'bool',
            customFieldConfigType: 'checkbox',
            fieldName: 'custom_first_tab_i_am_a_checkbox_field',
            entityCustomFieldValueBefore: true,
            entityCustomFieldValueAfter: false,
            componentName: 'sw-field',
            componentLabel: 'I am a checkbox field',
            componentConfigAddition: {},
            domFallbackValue: false,
            fallbackValue: false,
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.checked).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="checkbox"]',
            domFieldValueBefore: true,
            domFieldValueSelectorAfter: 'input[type="checkbox"]',
            domFieldValueAfter: false,
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="checkbox"]').trigger('click');
                await customField.find('input[type="checkbox"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'active/inactive switch field',
            customFieldType: 'bool',
            customFieldConfigType: 'switch',
            fieldName: 'custom_first_tab_i_am_a_switch_field',
            entityCustomFieldValueBefore: true,
            entityCustomFieldValueAfter: false,
            componentName: 'sw-field',
            componentLabel: 'I am a switch field',
            componentConfigAddition: {},
            domFallbackValue: false,
            fallbackValue: false,
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.checked).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="checkbox"]',
            domFieldValueBefore: true,
            domFieldValueSelectorAfter: 'input[type="checkbox"]',
            domFieldValueAfter: false,
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="checkbox"]').trigger('click');
                await customField.find('input[type="checkbox"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'text editor field',
            customFieldType: 'html',
            customFieldConfigType: 'textEditor',
            fieldName: 'custom_first_tab_i_am_a_text_editor_field',
            entityCustomFieldValueBefore: '<p>Old and gold</p>',
            entityCustomFieldValueAfter: '<p>Fresh and new</p>',
            componentName: 'sw-text-editor',
            componentLabel: 'I am a text editor field',
            componentConfigAddition: {},
            domFallbackValue: '',
            fallbackValue: '',
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="textEditor"]',
            domFieldValueBefore: '<p>Old and gold</p>',
            domFieldValueSelectorAfter: 'input[type="textEditor"]',
            domFieldValueAfter: '<p>Fresh and new</p>',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="textEditor"]').setValue('<p>Fresh and new</p>');
                await customField.find('input[type="textEditor"]').trigger('change');
            },
        },
        {
            testFieldLabel: 'colorpicker field',
            customFieldType: 'text',
            customFieldConfigType: 'colorpicker',
            fieldName: 'custom_first_tab_i_am_a_colorpicker_field',
            entityCustomFieldValueBefore: '#dd3c3c',
            entityCustomFieldValueAfter: '#48e8e8',
            componentName: 'sw-field',
            componentLabel: 'I am a colorpicker field',
            componentConfigAddition: {},
            domFallbackValue: '',
            fallbackValue: '',
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.element.value).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: 'input[type="text"]',
            domFieldValueBefore: '#dd3c3c',
            domFieldValueSelectorAfter: 'input[type="text"]',
            domFieldValueAfter: '#48e8e8',
            changeValueFunction: async (customField) => {
                // change input value
                await customField.find('input[type="text"]').setValue('#48e8e8');
                await customField.find('input[type="text"]').trigger('change');
                await flushPromises();
            },
        },
        {
            testFieldLabel: 'entity single select',
            customFieldType: 'select',
            customFieldConfigType: 'entity',
            fieldName: 'custom_first_tab_i_am_an_entity_single_select',
            entityCustomFieldValueBefore: uuid.get('Germany'),
            entityCustomFieldValueAfter: uuid.get('Vietnam'),
            componentName: 'sw-entity-single-select',
            componentLabel: 'I am an entity single select field',
            componentConfigAddition: {
                entity: 'country',
            },
            domFallbackValue: '',
            fallbackValue: [],
            domFieldValueSelectorExpectation: (domFieldValue, domFieldValueBefore) => {
                expect(domFieldValue.text()).toBe(domFieldValueBefore);
            },
            domFieldValueSelectorBefore: '.sw-entity-single-select__selection-text',
            domFieldValueBefore: 'Germany',
            domFieldValueSelectorAfter: '.sw-entity-single-select__selection-text',
            domFieldValueAfter: 'Vietnam',
            changeValueFunction: async (customField) => {
                // open select field
                await customField.find('.sw-entity-single-select__selection').trigger('click');
                await flushPromises();

                // check if second option exists
                const secondChoiceOption = customField.find('.sw-select-option--1');
                expect(secondChoiceOption.isVisible()).toBe(true);

                // click on second option
                await secondChoiceOption.trigger('click');
            },
        },
    ];

    beforeAll(() => {
        Shopware.Utils.debounce = (fn) => {
            return fn;
        };
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper({
            entity: {},
            sets: [],
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should inherit the value from parent entity', async () => {
        const props = {
            sets: createEntityCollection([{
                id: 'example',
                name: 'example',
                config: {},
                customFields: [{
                    name: 'customFieldName',
                    type: 'text',
                    config: {
                        label: 'configFieldLabel',
                    },
                }],
            }]),
            entity: {
                customFields: {
                    customFieldName: null,
                },
                customFieldSetSelectionActive: null,
                customFieldSets: createEntityCollection(),
            },
            parentEntity: {
                id: 'parentId',
                translated: {
                    customFields: {
                        customFieldName: 'inherit me',
                    },
                },
                customFieldSetSelectionActive: null,
                customFieldSets: [],
            },
        };
        wrapper = await createWrapper(props);
        await flushPromises();

        const customFieldEl = wrapper.find('.sw-inherit-wrapper input[name=customFieldName]');
        expect(customFieldEl.exists()).toBe(true);
        expect(customFieldEl.element.value).toBe('inherit me');
    });

    it('should not filter custom field sets when selection not active', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSetSelectionActive: true,
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                getEntityName: () => {
                    return 'product';
                },
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: false,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no customFieldSets column', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSetSelectionActive: null,
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should render the correct tab label given from the config', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSetSelectionActive: null,
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {
                    label: {
                        'en-GB': 'Set 1 Label',
                    },
                },
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
        expect(tabs.at(0).text()).toBe('Set 1 Label');
    });

    it('should render the fallback tab label when no label exists in the config', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSetSelectionActive: null,
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {
                    label: {
                        'en-GB': null,
                    },
                },
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
        expect(tabs.at(0).text()).toBe('set1');
    });

    it('should not filter custom field sets when entity has no customFieldSetSelectionActive column', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    // eslint-disable-next-line max-len
    it('should not filter custom field sets when entity has no parent and customFieldSetSelectionActive not set', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null,
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    // eslint-disable-next-line max-len
    it('should not filter custom field sets when customFieldSetSelectionActive not set and parent has no selection', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null,
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null,
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label',
                    },
                }],
            }]),
            parentEntity: {
                id: 'parentId',
            },
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should initialize new custom fields on entity change', async () => {
        const props = {
            entity: {
                customFieldSetSelectionActive: false,
                customFieldSets: undefined,
            },
            sets: createEntityCollection([{
                name: 'set1',
                id: 'set1',
                position: 2,
            }, {
                name: 'set2',
                id: 'set2',
                position: 1,
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        const spyInitializeCustomFields = jest.spyOn(wrapper.vm, 'initializeCustomFields');

        wrapper.vm.onChangeCustomFieldSetSelectionActive();

        await flushPromises();

        expect(spyInitializeCustomFields).toHaveBeenCalledTimes(1);
    });

    it('should sort sets by position', async () => {
        const props = {
            entity: {
                customFieldSetSelectionActive: false,
            },
            sets: createEntityCollection([{
                name: 'set1',
                id: 'set1',
                position: 2,
            }, {
                name: 'set2',
                id: 'set2',
                position: 1,
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        expect(wrapper.vm.visibleCustomFieldSets.first().name).toBe('set2');
    });

    it('should show the tabs', async () => {
        wrapper = await createWrapper({
            entity: {},
            parentEntity: {},
            sets: [
                {
                    id: uuid.get('custom_sports'),
                    name: 'custom_sports',
                    position: 1,
                    config: { label: { 'en-GB': 'Sports' } },
                    customFields: [],
                },
                {
                    id: uuid.get('custom_clothing'),
                    name: 'custom_clothing',
                    position: 1,
                    config: { label: { 'en-GB': 'Clothing' } },
                    customFields: [],
                },
            ],
        });

        expect(wrapper.find('.sw-tab--name-custom_sports').text()).toContain('Sports');
        expect(wrapper.find('.sw-tab--name-custom_clothing').text()).toContain('Clothing');
    });

    it('should contain the right fields for each tab', async () => {
        wrapper = await createWrapper({
            entity: {},
            parentEntity: {},
            sets: [
                {
                    id: uuid.get('custom_sports'),
                    name: 'custom_sports',
                    position: 1,
                    config: { label: { 'en-GB': 'Sports' } },
                    customFields: [
                        {
                            active: true,
                            name: 'custom_sports_football',
                            type: 'text',
                            config: {
                                customFieldPosition: 1,
                                customFieldType: 'text',
                                componentName: 'sw-field',
                                type: 'text',
                            },
                        },
                        {
                            active: true,
                            name: 'custom_sports_score',
                            type: 'float',
                            config: {
                                type: 'number',
                                label: { 'en-GB': 'qui et vel' },
                                numberType: 'float',
                                placeholder: { 'en-GB': 'Type a floating point number...' },
                                componentName: 'sw-field',
                                customFieldType: 'number',
                                customFieldPosition: 1,
                            },
                        },
                    ],
                },
                {
                    id: uuid.get('custom_clothing'),
                    name: 'custom_clothing',
                    position: 1,
                    config: { label: { 'en-GB': 'Clothing' } },
                    customFields: [
                        {
                            active: true,
                            name: 'custom_sports_soccer',
                            type: 'text',
                            config: {
                                customFieldPosition: 1,
                                customFieldType: 'text',
                                componentName: 'sw-field',
                                type: 'text',
                            },
                        },
                    ],
                },
            ],
        });

        await flushPromises();

        // get tab contents
        const tabContentSports = wrapper.find('.sw-custom-field-set-renderer-tab-content__custom_sports');
        const tabContentClothing = wrapper.find('.sw-custom-field-set-renderer-tab-content__custom_clothing');

        // check if tabs exists
        expect(tabContentSports.exists()).toBe(true);
        expect(tabContentClothing.exists()).toBe(true);

        // check if only the content of the active tab is visible
        expect(tabContentSports.isVisible()).toBe(true);
        expect(tabContentClothing.isVisible()).toBe(false);

        // get fields for sports tab
        const footballField = tabContentSports.findComponent('.sw-form-field-renderer-input-field__custom_sports_football');
        const scoreField = tabContentSports.findComponent('.sw-form-field-renderer-input-field__custom_sports_score');

        expect(footballField.exists()).toBe(true);
        expect(scoreField.exists()).toBe(true);
        expect(footballField.isVisible()).toBe(true);
        expect(scoreField.isVisible()).toBe(true);

        // check if fields get render correctly
        expect(footballField.props().config.componentName).toBe('sw-field');
        expect(footballField.props().config.type).toBe('text');

        expect(scoreField.props().config.componentName).toBe('sw-field');
        expect(scoreField.props().config.type).toBe('number');

        // get fields for clothing tab
        const soccerField = tabContentClothing.findComponent('.sw-form-field-renderer-input-field__custom_sports_soccer');
        expect(soccerField.exists()).toBe(true);
        expect(soccerField.isVisible()).toBe(false);

        // check if fields get render correctly
        expect(soccerField.props().config.componentName).toBe('sw-field');
        expect(soccerField.props().config.type).toBe('text');

        // click on clothing tab
        await wrapper.find('.sw-tab--name-custom_clothing').trigger('click');

        // check if active content changes
        expect(tabContentSports.isVisible()).toBe(false);
        expect(tabContentClothing.isVisible()).toBe(true);

        // check if fields are changing
        expect(footballField.isVisible()).toBe(false);
        expect(scoreField.isVisible()).toBe(false);
        expect(soccerField.isVisible()).toBe(true);
    });

    it('should load the current active tab', async () => {
        wrapper = await createWrapper({
            entity: {},
            parentEntity: {},
            sets: [
                {
                    id: uuid.get('custom_sports'),
                    name: 'custom_sports',
                    position: 1,
                    config: { label: { 'en-GB': 'Sports' } },
                    customFields: [
                    ],
                },
                {
                    id: uuid.get('custom_clothing'),
                    name: 'custom_clothing',
                    position: 1,
                    config: { label: { 'en-GB': 'Clothing' } },
                    customFields: [
                    ],
                },
            ],
        });

        // get tab contents
        const tabContentSports = wrapper.find('.sw-custom-field-set-renderer-tab-content__custom_sports');
        const tabContentClothing = wrapper.find('.sw-custom-field-set-renderer-tab-content__custom_clothing');

        // check if tabs exists
        expect(tabContentSports.exists()).toBe(true);
        expect(tabContentClothing.exists()).toBe(true);

        // check if only the content of the active tab is visible
        expect(tabContentSports.isVisible()).toBe(true);
        expect(tabContentClothing.isVisible()).toBe(false);

        await flushPromises();

        // get fields for sports & clothing tab
        const footballField = tabContentSports.find('.sw-form-field-renderer-input-field__custom_sports_football');
        const scoreField = tabContentSports.find('.sw-form-field-renderer-input-field__custom_sports_score');
        let nameField = tabContentClothing.find('.sw-form-field-renderer-input-field__custom_clothing_name');

        expect(nameField.exists()).toBe(false);
        expect(footballField.exists()).toBe(true);
        expect(scoreField.exists()).toBe(true);
        expect(footballField.isVisible()).toBe(true);
        expect(scoreField.isVisible()).toBe(true);

        // click on clothing tab
        await wrapper.find('.sw-tab--name-custom_clothing').trigger('click');

        await flushPromises();

        // get fields for clothing tab
        nameField = tabContentClothing.find('.sw-form-field-renderer-input-field__custom_clothing_name');

        // check if active content changes
        expect(tabContentSports.isVisible()).toBe(false);
        expect(tabContentClothing.isVisible()).toBe(true);

        // check if fields are changing
        expect(footballField.isVisible()).toBe(false);
        expect(scoreField.isVisible()).toBe(false);
        expect(nameField.exists()).toBe(true);
        expect(nameField.isVisible()).toBe(true);
    });

    it('should not assign empty custom fields to the given translated entity entry', async () => {
        const props = {
            entity: {
                customFields: null,
                customFieldSetSelectionActive: null,
                translated: {
                    customFields: {},
                },
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {
                    label: {
                        'en-GB': 'Set 1 Label',
                    },
                },
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            }]),
            showCustomFieldSetSelection: true,
        };

        wrapper = await createWrapper(props);

        await flushPromises();

        const entityCustomFields = wrapper.vm.entity.customFields;
        expect(entityCustomFields).toBeNull();
    });

    /**
     * Iterate through each possible custom field and check if everything works as expected
     */
    configuredFields
        .forEach(({
            testFieldLabel,
            fieldName,
            customFieldType,
            customFieldConfigType,
            entityCustomFieldValueBefore,
            entityCustomFieldValueAfter,
            componentName,
            componentLabel,
            componentConfigAddition,
            domFallbackValue,
            fallbackValue,
            domFieldValueSelectorExpectation,
            domFieldValueSelectorBefore,
            domFieldValueBefore,
            domFieldValueSelectorAfter,
            domFieldValueAfter,
            changeValueFunction,
        }) => {
            it(`should render the custom field and update value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore,
                        },
                    },
                    parentEntity: {},
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                let domFieldValue = customField.find(domFieldValueSelectorBefore);

                // check if default value is set right
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueBefore);
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // change value of custom field
                await changeValueFunction(customField);

                // check if new choosen value is set right
                entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueAfter);

                domFieldValue = customField.find(domFieldValueSelectorAfter);
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueAfter);
            });

            it(`should render the custom field with parent value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {},
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {
                                [fieldName]: entityCustomFieldValueBefore,
                            },
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                const entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                const domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be undefined
                expect(entityValueForCustomField).toBeUndefined();

                // check if parent value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is visible
                const inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
            });

            it(`should render the custom field with his value when has also parent value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore,
                        },
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {
                                [fieldName]: entityCustomFieldValueAfter,
                            },
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                const entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                const domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be his value
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueBefore);

                // check if his value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is visible
                const inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show no inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
            });

            it(`should render the custom field with parent value and can remove inheritance when parent has value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {},
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {
                                [fieldName]: entityCustomFieldValueBefore,
                            },
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                let domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be undefined
                expect(entityValueForCustomField).toBeUndefined();

                // check if parent value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is visible
                let inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

                // click on switch
                await inheritanceSwitch.find('.sw-icon').trigger('click');
                await flushPromises();

                // check if entity value contains parent value and not undefined
                entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueBefore);

                // check if DOM value contains parent value
                domFieldValue = customField.find(domFieldValueSelectorBefore);
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is not inherit anymore
                inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
            });

            it(`should render the custom field with parent value and can remove inheritance when parent has no value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {},
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {},
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                let domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be undefined
                expect(entityValueForCustomField).toBeUndefined();

                // check if fallback value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFallbackValue);

                // check if inheritance switch is visible
                let inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

                // click on switch
                await inheritanceSwitch.find('.sw-icon').trigger('click');

                // check if entity value contains fallback value and not undefined
                entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                expect(entityValueForCustomField).toEqual(fallbackValue);

                // check if DOM value contains fallback value
                domFieldValue = customField.find(domFieldValueSelectorBefore);
                await domFieldValueSelectorExpectation(domFieldValue, domFallbackValue);

                // check if inheritance switch is not inherit anymore
                inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');
            });

            it(`should render the custom field with custom value and can restore inheritance when parent has value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore,
                        },
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {
                                [fieldName]: entityCustomFieldValueAfter,
                            },
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                let domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be defined
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueBefore);

                // check if his value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is visible
                let inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show no inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');

                // click on switch
                await inheritanceSwitch.find('.sw-icon').trigger('click');
                await flushPromises();

                // entity value should be null
                entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                expect(entityValueForCustomField).toBeNull();

                // check if parent value is visible
                domFieldValue = customField.find(domFieldValueSelectorAfter);
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueAfter);

                // check if inheritance switch is inherited
                inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
            });

            it(`should render the custom field with custom value and can restore inheritance when parent has no value: ${testFieldLabel}`, async () => {
                wrapper = await createWrapper({
                    entity: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore,
                        },
                    },
                    parentEntity: {
                        id: uuid.get('parentEntity'),
                        translated: {
                            customFields: {},
                        },
                    },
                    sets: [
                        {
                            id: uuid.get('custom_first_tab'),
                            name: 'custom_first_tab',
                            position: 1,
                            config: { label: { 'en-GB': 'First tab' } },
                            customFields: [
                                {
                                    active: true,
                                    name: fieldName,
                                    type: customFieldType,
                                    config: {
                                        customFieldPosition: 1,
                                        customFieldType: customFieldConfigType,
                                        type: customFieldConfigType,
                                        componentName: componentName,
                                        label: { 'en-GB': componentLabel },
                                        ...componentConfigAddition,
                                    },
                                },
                            ],
                        },
                    ],
                });

                await flushPromises();

                const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
                let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                let domFieldValue = customField.find(domFieldValueSelectorBefore);

                // entity value should be defined
                expect(entityValueForCustomField).toEqual(entityCustomFieldValueBefore);

                // check if his value is visible
                await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

                // check if inheritance switch is visible
                let inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.isVisible()).toBe(true);

                // check if switch show no inheritance
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-not-inherited');

                // click on switch
                await inheritanceSwitch.find('.sw-icon').trigger('click');
                await flushPromises();

                // entity value should be null
                entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
                expect(entityValueForCustomField).toBeNull();

                // check if parent value is visible
                domFieldValue = customField.find(domFieldValueSelectorAfter);
                await domFieldValueSelectorExpectation(domFieldValue, domFallbackValue);

                // check if inheritance switch is inherited
                inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
                expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
            });
        });

    it.each([
        { name: 'default', customFields: { field1: 'de' }, expected: 'de' },
        { name: 'empty', customFields: { field: null }, expected: undefined },
    ])('should not use the custom field translation as a fallback for input fields: $name', async ({ customFields, expected }) => {
        const props = {
            entity: {
                customFields,
                translated: {
                    customFields: {
                        field1: 'en',
                    },
                },
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {
                    label: {
                        'en-GB': 'Set 1 Label GB',
                        'de-DE': 'Set 1 Label DE',
                    },
                },
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label',
                    },
                }],
            }]),
        };

        wrapper = await createWrapper(props);
        await flushPromises();

        const inputField = wrapper.find('.sw-form-field-renderer-field__field1 input');
        expect(inputField.exists()).toBe(true);

        expect(inputField.attributes('value')).toBe(expected);
    });
});
