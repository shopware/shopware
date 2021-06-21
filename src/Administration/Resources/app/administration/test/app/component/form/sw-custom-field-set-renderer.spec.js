/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import Vue from 'vue';
import uuid from 'src/../test/_helper_/uuid';
import 'src/app/component/form/sw-custom-field-set-renderer';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/base/sw-button';
import 'src/app/component/base/sw-label';
import 'src/app/component/base/sw-tabs';
import 'src/app/component/base/sw-tabs-item';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-number-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-inheritance-switch';
import 'src/app/component/base/sw-icon';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-multi-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-selection-list';
import 'src/app/component/utils/sw-popover';
import 'src/app/component/base/sw-highlight-text';
import 'src/app/component/media/sw-media-field';
import 'src/app/component/media/sw-media-media-item';
import 'src/app/component/media/sw-media-base-item';
import 'src/app/component/media/sw-media-preview-v2';
import 'src/app/filter/media-name.filter';


function createEntityCollection(entities = []) {
    return new Shopware.Data.EntityCollection('collection', 'collection', {}, null, entities);
}

function createWrapper(props) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('popover', {});
    localVue.filter('mediaName', Shopware.Filter.getByName('mediaName'));

    return shallowMount(Shopware.Component.build('sw-custom-field-set-renderer'), {
        localVue,
        propsData: props,
        stubs: {
            'sw-button': Shopware.Component.build('sw-button'),
            'sw-label': Shopware.Component.build('sw-label'),
            'sw-tabs': Shopware.Component.build('sw-tabs'),
            'sw-tabs-item': Shopware.Component.build('sw-tabs-item'),
            'sw-inherit-wrapper': Shopware.Component.build('sw-inherit-wrapper'),
            'sw-inheritance-switch': Shopware.Component.build('sw-inheritance-switch'),
            'sw-form-field-renderer': Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-switch-field': Shopware.Component.build('sw-switch-field'),
            'sw-number-field': Shopware.Component.build('sw-number-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-entity-multi-select': true,
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': Shopware.Component.build('sw-field-error'),
            'sw-icon': {
                template: '<div class="sw-icon" @click="$emit(\'click\')"></div>'
            },
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-multi-select': Shopware.Component.build('sw-multi-select'),
            'sw-select-base': Shopware.Component.build('sw-select-base'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-select-selection-list': Shopware.Component.build('sw-select-selection-list'),
            'sw-popover': Shopware.Component.build('sw-popover'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-media-field': Shopware.Component.build('sw-media-field'),
            'sw-media-media-item': Shopware.Component.build('sw-media-media-item'),
            'sw-media-base-item': Shopware.Component.build('sw-media-base-item'),
            'sw-media-preview-v2': Shopware.Component.build('sw-media-preview-v2'),
            'sw-colorpicker': Shopware.Component.build('sw-text-field'),
            'sw-upload-listener': true,
            'sw-simple-search-field': true,
            'sw-loader': true,
            'sw-datepicker': Shopware.Component.build('sw-text-field'),
            'sw-text-editor': {
                props: ['value'],
                template: '<input type="text" :value="value" @change="$emit(\'change\', $event.target.value)"></input>'
            }
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
                                    id: uuid.get('media after')
                                },
                                {
                                    hasFile: true,
                                    fileName: 'media_before',
                                    fileExtension: 'jpg',
                                    id: uuid.get('media before')
                                }
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
                                    id: uuid.get('media before')
                                });
                            }

                            if (id === uuid.get('media after')) {
                                return Promise.resolve({
                                    hasFile: true,
                                    fileName: 'media_after',
                                    fileExtension: 'jpg',
                                    id: uuid.get('media after')
                                });
                            }
                        }

                        return Promise.resolve({});
                    }
                })
            },
            validationService: {},
            mediaService: {}
        }
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
                    { label: { 'en-GB': 'Second choice' }, value: 'second_choice' }
                ]
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

                // check if second option exists
                const secondChoiceOption = customField.find('.sw-select-option--second_choice');
                expect(secondChoiceOption.isVisible()).toBe(true);

                // click on second option
                await secondChoiceOption.trigger('click');
            }
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
                    { label: { 'en-GB': 'Second choice' }, value: 'second_choice' }
                ]
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

                // check if second option exists
                const secondChoiceOption = customField.find('.sw-select-option--second_choice');
                expect(secondChoiceOption.isVisible()).toBe(true);

                // click on second option
                await secondChoiceOption.trigger('click');
            }
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
            }
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
                await wrapper.vm.$nextTick();
                await customField.find('.sw-media-field__suggestion-list-entry:first-child .sw-media-base-item').trigger('click');
            }
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
            }
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
            }
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
            }
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
            }
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
            }
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
            }
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
                await wrapper.vm.$nextTick();
            }
        }
    ];

    beforeAll(() => {
        Shopware.Utils.debounce = () => {};
    });

    afterEach(async () => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = createWrapper({
            entity: {},
            sets: []
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
                        label: 'configFieldLabel'
                    }
                }]
            }]),
            entity: {
                customFields: {
                    customFieldName: null
                },
                customFieldSetSelectionActive: null,
                customFieldSets: createEntityCollection()
            },
            parentEntity: {
                id: 'parentId',
                translated: {
                    customFields: {
                        customFieldName: 'inherit me'
                    }
                },
                customFieldSetSelectionActive: null,
                customFieldSets: []
            }
        };
        wrapper = createWrapper(props);

        const customFieldEl = wrapper.find('.sw-inherit-wrapper input[name=customFieldName]');
        expect(customFieldEl.exists()).toBe(true);
        expect(customFieldEl.element.value).toBe('inherit me');
    });

    it('should not filter custom field sets when selection not active', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSetSelectionActive: true,
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: false
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no customFieldSets column', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should not filter custom field sets when entity has no customFieldSetSelectionActive column', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }])
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    // eslint-disable-next-line max-len
    it('should not filter custom field sets when entity has no parent and customFieldSetSelectionActive not set', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    // eslint-disable-next-line max-len
    it('should not filter custom field sets when customFieldSetSelectionActive not set and parent has no selection', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: null
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            parentEntity: {
                id: 'parentId'
            },
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(false);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(2);
        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(2);
    });

    it('should filter custom field sets when selection active and customFields selected', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: true,
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(true);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        expect(wrapper.vm.visibleCustomFieldSets[0].id).toBe('set2');

        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
    });

    it('should filter custom field sets from parent when inherited', async () => {
        const props = {
            entity: {
                customFields: {
                    field1: null
                },
                customFieldSets: createEntityCollection(),
                customFieldSetSelectionActive: null,
                getEntityName: () => {
                    return 'product';
                }
            },
            sets: createEntityCollection([{
                id: 'set1',
                name: 'set1',
                config: {},
                customFields: [{
                    name: 'field1',
                    type: 'text',
                    config: {
                        label: 'field1Label'
                    }
                }]
            },
            {
                id: 'set2',
                name: 'set2',
                config: {},
                customFields: [{
                    name: 'field2',
                    type: 'text',
                    config: {
                        label: 'field2Label'
                    }
                }]
            }]),
            parentEntity: {
                id: 'parent',
                customFieldSets: createEntityCollection([{ id: 'set2' }]),
                customFieldSetSelectionActive: true
            },
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        expect(wrapper.vm.filterCustomFields).toBe(true);
        expect(wrapper.vm.visibleCustomFieldSets).toHaveLength(1);
        expect(wrapper.vm.visibleCustomFieldSets[0].id).toBe('set2');

        const tabs = wrapper.findAll('.sw-tabs__content .sw-tabs-item');
        expect(tabs).toHaveLength(1);
    });

    it('should initialize new custom fields on entity change', async () => {
        const props = {
            entity: {
                customFieldSetSelectionActive: false,
                customFieldSets: undefined
            },
            sets: createEntityCollection([{
                name: 'set1',
                position: 2
            }, {
                name: 'set2',
                position: 1
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

        const spyInitializeCustomFields = jest.spyOn(wrapper.vm, 'initializeCustomFields');

        wrapper.vm.onChangeCustomFieldSetSelectionActive();

        await wrapper.vm.$nextTick();

        expect(spyInitializeCustomFields).toHaveBeenCalledTimes(1);
    });

    it('should sort sets by position', async () => {
        const props = {
            entity: {
                customFieldSetSelectionActive: false
            },
            sets: createEntityCollection([{
                name: 'set1',
                position: 2
            }, {
                name: 'set2',
                position: 1
            }]),
            showCustomFieldSetSelection: true
        };

        wrapper = createWrapper(props);

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
                    customFields: []
                },
                {
                    id: uuid.get('custom_clothing'),
                    name: 'custom_clothing',
                    position: 1,
                    config: { label: { 'en-GB': 'Clothing' } },
                    customFields: []
                }
            ]
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
                                type: 'text'
                            }
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
                                customFieldPosition: 1
                            }
                        }
                    ]
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
                                type: 'text'
                            }
                        }
                    ]
                }
            ]
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

        // get fields for sports tab
        const footballField = tabContentSports.find('.sw-form-field-renderer-input-field__custom_sports_football');
        const scoreField = tabContentSports.find('.sw-form-field-renderer-input-field__custom_sports_score');

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
        const soccerField = tabContentClothing.find('.sw-form-field-renderer-input-field__custom_sports_soccer');
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

    /**
     * Iterate through each possible custom field and check if everything works as expected
     */
    configuredFields.forEach(({
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
        changeValueFunction
    }) => {
        it(`should render the custom field and update value: ${testFieldLabel}`, async () => {
            wrapper = await createWrapper({
                entity: {
                    customFields: {
                        [fieldName]: entityCustomFieldValueBefore
                    }
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

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
                entity: Vue.observable({
                    customFields: {}
                }),
                parentEntity: Vue.observable({
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore
                        }
                    }
                }),
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

            const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
            const entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
            const domFieldValue = customField.find(domFieldValueSelectorBefore);

            // entity value should be undefined
            expect(entityValueForCustomField).toEqual(undefined);

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
                entity: Vue.observable({
                    customFields: {
                        [fieldName]: entityCustomFieldValueBefore
                    }
                }),
                parentEntity: Vue.observable({
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueAfter
                        }
                    }
                }),
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

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
                entity: Vue.observable({
                    customFields: {}
                }),
                parentEntity: {
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueBefore
                        }
                    }
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

            const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
            let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
            let domFieldValue = customField.find(domFieldValueSelectorBefore);

            // entity value should be undefined
            expect(entityValueForCustomField).toEqual(undefined);

            // check if parent value is visible
            await domFieldValueSelectorExpectation(domFieldValue, domFieldValueBefore);

            // check if inheritance switch is visible
            let inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.isVisible()).toBe(true);

            // check if switch show inheritance
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');

            // click on switch
            await inheritanceSwitch.find('.sw-icon').trigger('click');
            await wrapper.vm.$nextTick();

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
                entity: Vue.observable({
                    customFields: {}
                }),
                parentEntity: Vue.observable({
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {}
                    }
                }),
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

            const customField = wrapper.find(`.sw-form-field-renderer-field__${fieldName}`);
            let entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
            let domFieldValue = customField.find(domFieldValueSelectorBefore);

            // entity value should be undefined
            expect(entityValueForCustomField).toEqual(undefined);

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
                entity: Vue.observable({
                    customFields: {
                        [fieldName]: entityCustomFieldValueBefore
                    }
                }),
                parentEntity: Vue.observable({
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {
                            [fieldName]: entityCustomFieldValueAfter
                        }
                    }
                }),
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

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
            await wrapper.vm.$nextTick();

            // entity value should be null
            entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
            expect(entityValueForCustomField).toEqual(null);

            // check if parent value is visible
            domFieldValue = customField.find(domFieldValueSelectorAfter);
            await domFieldValueSelectorExpectation(domFieldValue, domFieldValueAfter);

            // check if inheritance switch is inherited
            inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
        });

        it(`should render the custom field with custom value and can restore inheritance when parent has no value: ${testFieldLabel}`, async () => {
            wrapper = await createWrapper({
                entity: Vue.observable({
                    customFields: {
                        [fieldName]: entityCustomFieldValueBefore
                    }
                }),
                parentEntity: Vue.observable({
                    id: uuid.get('parentEntity'),
                    translated: {
                        customFields: {}
                    }
                }),
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
                                    ...componentConfigAddition
                                }
                            }
                        ]
                    }
                ]
            });

            await wrapper.vm.$nextTick();

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
            await wrapper.vm.$nextTick();

            // entity value should be null
            entityValueForCustomField = wrapper.vm.entity.customFields[fieldName];
            expect(entityValueForCustomField).toEqual(null);

            // check if parent value is visible
            domFieldValue = customField.find(domFieldValueSelectorAfter);
            await domFieldValueSelectorExpectation(domFieldValue, domFallbackValue);

            // check if inheritance switch is inherited
            inheritanceSwitch = wrapper.find('.sw-inheritance-switch');
            expect(inheritanceSwitch.classes()).toContain('sw-inheritance-switch--is-inherited');
        });
    });
});
